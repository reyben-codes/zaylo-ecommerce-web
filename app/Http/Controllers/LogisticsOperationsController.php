<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesLogisticsParcels;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Services\ChatPhotos;
use App\Services\LogisticsMessenger;
use App\Services\ReturnPickups;
use App\Services\ShipmentWorkflow;
use App\Support\ParcelView;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The logistics partner's sorting center: confirm seller pickups, receive and sort parcels at the hub,
 * assign riders by area, and follow every delivery to the end.
 */
class LogisticsOperationsController extends Controller
{
    use QueriesLogisticsParcels;

    /** Active parcels with no update for this long are flagged on the monitoring page. */
    private const STALE_HOURS = 24;

    public function __construct(private ShipmentWorkflow $workflow, private LogisticsMessenger $messenger, private ReturnPickups $returns) {}

    /** Seller pickup requests to confirm or decline, and confirmed pickups to collect. */
    public function pickups(Request $request)
    {
        $hub = ShipmentWorkflow::hasHubSteps();
        $tab = $hub && $request->query('tab') === 'scheduled' ? 'scheduled' : 'requests';
        $search = trim((string) $request->query('search', ''));
        $shipments = $this->search(ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), [$tab === 'scheduled' ? 'confirmed' : 'ready']), $search)
            ->when($tab === 'scheduled', fn (Builder $query) => $query->orderBy('pickup_scheduled_for'))
            ->oldest()->paginate(15)->withQueryString();

        return view('logistics.operations.pickups', [
            'shipments' => $shipments,
            'search' => $search,
            'tab' => $tab,
            'hub' => $hub,
            'counts' => [
                'requests' => ShipmentWorkflow::whereStage($this->parcels(), ['ready'])->count(),
                'scheduled' => $hub ? ShipmentWorkflow::whereStage($this->parcels(), ['confirmed'])->count() : 0,
            ],
        ]);
    }

    /** Collected from sellers and on the way to the hub. */
    public function incoming(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $shipments = $this->search(ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), ['picked_up']), $search)
            ->oldest()->paginate(15)->withQueryString();

        return view('logistics.operations.incoming', compact('shipments', 'search'));
    }

    /** Parcels received at the hub, to sort into delivery areas. */
    public function sorting(Request $request)
    {
        if (! ShipmentWorkflow::hasHubSteps()) {
            return redirect()->route('logistics.assignments')->with('status', 'Sorting turns on after the latest database update (php artisan migrate). Until then, parcels go straight from the hub to a rider.');
        }
        $search = trim((string) $request->query('search', ''));
        $shipments = $this->search(ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), ['in_transit']), $search)
            ->oldest()->paginate(15)->withQueryString();

        return view('logistics.operations.sorting', [
            'shipments' => $shipments,
            'search' => $search,
            'bins' => $this->bins(),
            'knownAreas' => $this->knownAreas(),
        ]);
    }

    public function scan(Request $request)
    {
        $tracking = trim((string) $request->query('tracking', ''));
        $shipment = null;
        $returnCase = null;

        if ($tracking !== '') {
            $shipment = $this->withParcelDetails($this->parcels())
                ->where(fn (Builder $query) => $this->matchTracking($query, $tracking, exact: true))
                ->first();

            if (! $shipment) {
                $returnCase = Dispute::where('return_tracking_number', $tracking)
                    ->orWhere(function ($q) use ($tracking) {
                        if (str_starts_with($tracking, 'RET-')) {
                            $q->where('return_tracking_number', 'like', '%'.$tracking.'%');
                        }
                    })
                    ->with(['order.items', 'order.buyer', 'order.seller'])
                    ->first();
            }
        }

        if ($returnCase && ! $this->returns->handledBy($returnCase, $this->logisticsProvider())) {
            $returnCase = null;
        }

        return view('logistics.operations.scan', [
            'tracking' => $tracking,
            'shipment' => $shipment,
            'returnCase' => $returnCase,
            'riders' => $shipment || $returnCase ? $this->availableRiders() : collect(),
            'knownAreas' => $shipment && ShipmentWorkflow::awaitingSort($shipment) ? $this->knownAreas() : collect(),
        ]);
    }

    /**
     * Returned items this partner brings back to sellers: pickups to give to a rider, items on the way to or at the
     * hub, and ones handed back. The rider scans a pickup at the buyer's door; the hub takes it from there.
     */
    public function returns(Request $request)
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['active', 'done'])]])['tab'] ?? 'active';
        $cases = $this->returns->forProvider($this->logisticsProvider());
        $done = fn (Dispute $case) => $case->returnReceived() || $case->status === 'rejected';

        return view('logistics.operations.returns', [
            'tab' => $tab,
            'cases' => $cases->filter(fn (Dispute $case) => $tab === 'done' ? $done($case) : ! $done($case))->values(),
            'counts' => ['active' => $cases->reject($done)->count(), 'done' => $cases->filter($done)->count()],
            'riders' => $this->availableRiders(),
        ]);
    }

    /** A pickup return goes to one of the partner's riders, who collects it from the buyer. */
    public function assignReturn(Request $request, Dispute $dispute)
    {
        $this->ownsReturn($dispute);
        $rider = $this->chosenRider($request);
        $this->returns->assign($dispute, $rider, $request->user());

        return back()->with('status', 'Return '.$dispute->return_tracking_number.' assigned to '.$rider->user->name.'.');
    }

    /** A return parcel scanned at the hub: received there, sent back out, or handed to the seller. */
    public function updateReturnScan(Request $request, Dispute $dispute)
    {
        $this->ownsReturn($dispute);
        $data = $request->validate([
            'return_status' => ['required', Rule::in(array_keys(ReturnPickups::LOGISTICS_LABELS))],
        ] + ChatPhotos::rules(), ChatPhotos::messages());
        // Receiving it at the hub needs a photo of the item as it arrived.
        $this->returns->advance($dispute, $data['return_status'], $request->user(), $request->file('photo'));

        return back()->with('status', 'Return '.$dispute->return_tracking_number.': '.ReturnPickups::LOGISTICS_LABELS[$data['return_status']].'.');
    }

    private function ownsReturn(Dispute $dispute): void
    {
        abort_unless($dispute->hasReturnParcel() && $this->returns->handledBy($dispute, $this->logisticsProvider()), 404);
    }

    /** Sorted parcels waiting for a rider, by delivery area: give a whole area to one rider, or parcel by parcel. */
    public function assignments(Request $request)
    {
        $area = trim((string) $request->query('area', ''));
        $hub = ShipmentWorkflow::hasHubSteps();
        $shipments = $this->awaitingRiderQuery()
            ->when($area !== '', fn (Builder $query) => $hub ? $query->where('sort_area', $area) : $this->inArea($query, $area))
            ->oldest()->paginate(15)->withQueryString();

        return view('logistics.operations.assignments', [
            'shipments' => $shipments,
            'area' => $area,
            'hub' => $hub,
            'bins' => $hub ? $this->bins() : collect(),
            'riders' => $this->availableRiders(),
            'workload' => $this->riderWorkload(),
        ]);
    }

    /** Every parcel, with its current step and what can happen next. */
    public function deliveries(Request $request)
    {
        $filters = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(ShipmentWorkflow::STAGES))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $stage = $filters['stage'] ?? null;
        $search = trim($filters['search'] ?? '');
        $shipments = $this->search($this->withParcelDetails($this->parcels()), $search)
            ->when($stage, fn (Builder $query) => ShipmentWorkflow::whereStage($query, [$stage]))
            ->latest('updated_at')->paginate(15)->withQueryString();

        return view('logistics.operations.deliveries', [
            'shipments' => $shipments,
            'stage' => $stage,
            'search' => $search,
            'counts' => $this->parcelStageCounts(),
        ]);
    }

    /** Live view of riders on the road and parcels that need attention. */
    public function monitoring()
    {
        $staleBefore = now()->subHours(self::STALE_HOURS);
        $attention = $this->withParcelDetails($this->parcels())
            ->where(fn (Builder $any) => $any
                ->where(fn (Builder $failed) => ShipmentWorkflow::whereStage($failed, ['failed']))
                ->orWhere(fn (Builder $stale) => ShipmentWorkflow::whereStage($stale, array_diff(ShipmentWorkflow::ACTIVE, ['failed']))
                    ->where('updated_at', '<', $staleBefore)))
            ->oldest('updated_at')->limit(20)->get();
        $onTheRoad = ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), ['assigned', 'out_for_delivery'])
            ->oldest('updated_at')->get();

        return view('logistics.operations.monitoring', [
            'workload' => $this->riderWorkload(),
            'onTheRoad' => $onTheRoad,
            'attention' => $attention,
            'staleHours' => self::STALE_HOURS,
            'deliveredToday' => ShipmentWorkflow::whereStage($this->parcels(), ['delivered'])
                ->where('updated_at', '>=', now('Asia/Manila')->startOfDay()->utc())->count(),
        ]);
    }

    public function reports(Request $request)
    {
        [$from, $to, $filters] = $this->reportRange($request);
        $report = $this->buildReport($from, $to, $filters);

        return view('logistics.operations.reports', $report + [
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'item' => $filters['item'] ?? '',
            'stage' => $filters['stage'] ?? '',
            'selectedRider' => $filters['rider'] ?? null,
            'riders' => $this->availableRiders(),
            'stages' => ShipmentWorkflow::STAGES,
        ]);
    }

    /** Parcels listed in the PDF; larger periods are summarised and the list notes it. */
    private const PDF_PARCEL_LIMIT = 500;

    /** The same report as a printable PDF, with the parcel list. */
    public function exportReport(Request $request)
    {
        [$from, $to, $filters] = $this->reportRange($request);
        $report = $this->buildReport($from, $to, $filters);
        $parcels = $this->withParcelDetails($this->reportQuery($from, $to, $filters))->oldest()->limit(self::PDF_PARCEL_LIMIT)->get()
            ->map(fn (Shipment $shipment) => ParcelView::for($shipment) + [
                'created' => $shipment->created_at?->timezone('Asia/Manila')->format('M j, Y'),
            ]);
        $label = fn (?string $date) => $date ? Carbon::parse($date, 'Asia/Manila')->format('M j, Y') : null;
        $period = match (true) {
            isset($filters['from'], $filters['to']) => $label($filters['from']).' – '.$label($filters['to']),
            isset($filters['from']) => 'From '.$label($filters['from']),
            isset($filters['to']) => 'Up to '.$label($filters['to']),
            default => 'All time',
        };

        $pdf = Pdf::loadView('logistics.operations.report-pdf', $report + [
            'provider' => $this->logisticsProvider(),
            'period' => $period,
            'item' => $filters['item'] ?? null,
            'stage' => $filters['stage'] ?? null,
            'selectedRider' => $filters['rider'] ?? null,
            'generatedAt' => now('Asia/Manila'),
            'generatedBy' => $request->user()->name,
            'parcels' => $parcels,
            'parcelLimit' => self::PDF_PARCEL_LIMIT,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);
        // Page numbers are drawn on the finished pages (embedded PHP stays disabled in dompdf).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 24, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.42, 0.37, 0.33]);

        $itemSlug = ! empty($filters['item']) ? '-'.Str::slug($filters['item']) : '';
        $file = 'zaylo-logistics-report'.$itemSlug.'-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? now('Asia/Manila')->toDateString()).'.pdf';

        return $pdf->download($file);
    }

    /** Confirm a seller's pickup request for a date, or decline it with a reason. */
    public function pickup(Request $request, Shipment $shipment)
    {
        $this->ownsParcel($shipment, orOpenRequest: true);
        abort_unless(ShipmentWorkflow::hasHubSteps(), 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'decline'])],
            'pickup_date' => ['exclude_unless:decision,confirm', 'required', 'date', 'after_or_equal:'.now('Asia/Manila')->toDateString(), 'before_or_equal:'.now('Asia/Manila')->addDays(14)->toDateString()],
            'reason' => ['exclude_unless:decision,decline', 'required', 'string', 'min:5', 'max:500'],
        ], [
            'pickup_date.required' => 'Choose the pickup date.',
            'pickup_date.after_or_equal' => 'The pickup date cannot be in the past.',
            'pickup_date.before_or_equal' => 'Schedule the pickup within the next 14 days.',
            'reason.required' => 'Tell the seller why the pickup is declined.',
        ]);
        $shipment->loadMissing($this->detailRelations());
        $parcel = ParcelView::for($shipment);
        $sellerId = ShipmentWorkflow::sellerUserId($shipment);
        $provider = $this->logisticsProvider();

        if ($data['decision'] === 'confirm') {
            $date = Carbon::parse($data['pickup_date'], 'Asia/Manila');
            DB::transaction(function () use ($shipment, $date, $provider, $request) {
                // An open request (no partner named) goes to the first partner to confirm it.
                if ($provider && Shipment::tracksProvider() && $shipment->logistics_provider_id === null) {
                    $taken = Shipment::whereKey($shipment->id)->whereNull('logistics_provider_id')->update(['logistics_provider_id' => $provider->id]);
                    if (! $taken) {
                        throw ValidationException::withMessages(['pickup_date' => 'Another logistics partner has already taken this pickup.']);
                    }
                }
                $this->workflow->confirmPickup($shipment, $date, $request->user());
            });
            $provider && $this->messenger->notify($provider, $sellerId, "Pickup confirmed for order {$parcel['order']} (parcel {$parcel['tracking']}). We will collect it on {$date->format('l, M j')}. Please have it packed and labeled.");

            return back()->with('status', "Pickup for {$parcel['tracking']} confirmed for {$date->format('M j')}.");
        }

        $this->workflow->declinePickup($shipment, $data['reason'], $request->user());
        $provider && $this->messenger->notify($provider, $sellerId, "We could not accept the pickup for order {$parcel['order']}: {$data['reason']} The order is back in your Processing list; mark it ready to ship again once it is fixed.");

        return back()->with('status', "Pickup for {$parcel['tracking']} declined. The seller has been told why.");
    }

    public function sort(Request $request, Shipment $shipment)
    {
        $this->ownsParcel($shipment);
        abort_unless(ShipmentWorkflow::hasHubSteps(), 404);
        $data = $request->validate(['area' => ['required', 'string', 'min:2', 'max:120']], ['area.required' => 'Enter the delivery area for this parcel.']);
        $area = $this->normalizeArea($data['area']);
        $this->workflow->sort($shipment, $area, $request->user());

        return back()->with('status', 'Parcel '.$this->tracking($shipment).' sorted for '.$area.'.');
    }

    public function advance(Request $request, Shipment $shipment)
    {
        $this->ownsParcel($shipment);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ShipmentWorkflow::ACTION_LABELS))],
            // Collecting from the seller needs the waybill in hand: its code is scanned or typed.
            'tracking' => ['exclude_unless:status,picked_up', 'required', 'string', 'max:100'],
        ], [
            'tracking.required' => "Scan or type the tracking number on the parcel's waybill to confirm you have it.",
        ]);
        // Going out, delivering and reporting a failed attempt belong to the assigned rider, in the rider app.
        if (ShipmentWorkflow::riderOf($shipment) && in_array($data['status'], ShipmentWorkflow::RIDER_ONLY, true)) {
            throw ValidationException::withMessages(['status' => 'Only the assigned rider can update parcel '.$this->tracking($shipment).' to "'.ShipmentWorkflow::label($data['status']).'", from the rider app.']);
        }
        if ($data['status'] === 'picked_up' && strcasecmp(trim($data['tracking']), $this->tracking($shipment)) !== 0) {
            throw ValidationException::withMessages(['tracking' => 'That code does not match parcel '.$this->tracking($shipment).'. Check that you have the right parcel.']);
        }
        $this->workflow->advance($shipment, $data['status'], $request->user());

        return back()->with('status', 'Parcel '.$this->tracking($shipment).': '.ShipmentWorkflow::label($data['status']).'.');
    }

    public function assign(Request $request, Shipment $shipment)
    {
        $this->ownsParcel($shipment);
        $rider = $this->chosenRider($request);
        $this->workflow->assign($shipment, $rider, $request->user());

        return back()->with('status', 'Parcel '.$this->tracking($shipment).' assigned to '.$rider->user->name.'.');
    }

    /** Give every parcel waiting in one delivery area to the same rider. */
    public function assignArea(Request $request)
    {
        abort_unless(ShipmentWorkflow::hasHubSteps(), 404);
        $area = trim((string) $request->validate(['area' => ['required', 'string', 'max:120']])['area']);
        $rider = $this->chosenRider($request);
        $parcels = $this->awaitingRiderQuery()->where('sort_area', $area)->get();
        if ($parcels->isEmpty()) {
            throw ValidationException::withMessages(['area' => 'No parcels in '.$area.' are waiting for a rider.']);
        }
        foreach ($parcels as $shipment) {
            $this->workflow->assign($shipment, $rider, $request->user());
        }

        return back()->with('status', $parcels->count().' '.str('parcel')->plural($parcels->count()).' in '.$area.' assigned to '.$rider->user->name.'.');
    }

    private function chosenRider(Request $request): Rider
    {
        $data = $request->validate(['rider_id' => ['required', 'integer']], ['rider_id.required' => 'Choose a rider.']);
        $rider = $this->availableRiders()->firstWhere('id', (int) $data['rider_id']);
        if (! $rider) {
            throw ValidationException::withMessages(['rider_id' => 'That rider is not available for assignments.']);
        }

        return $rider;
    }

    private function awaitingRiderQuery(): Builder
    {
        return ShipmentWorkflow::whereStage($this->withParcelDetails($this->parcels()), [ShipmentWorkflow::assignableStage()])
            ->where(fn (Builder $query) => $this->withoutRider($query));
    }

    /** Sorted parcels waiting for a rider, counted per delivery area. */
    private function bins(): Collection
    {
        if (! ShipmentWorkflow::hasHubSteps()) {
            return collect();
        }

        return ShipmentWorkflow::whereStage($this->parcels(), ['sorted'])
            ->where(fn (Builder $query) => $this->withoutRider($query))
            ->selectRaw('sort_area, count(*) as total, min(sorted_at) as since')
            ->groupBy('sort_area')->orderByDesc('total')->orderBy('sort_area')
            ->get()
            ->map(fn ($row) => ['area' => $row->sort_area, 'total' => (int) $row->total]);
    }

    /** Areas used before, offered while sorting so the same place is always spelled the same way. */
    private function knownAreas(): Collection
    {
        return $this->parcels()->whereNotNull('sort_area')->distinct()->orderBy('sort_area')->limit(200)->pluck('sort_area');
    }

    private function normalizeArea(string $area): string
    {
        $area = preg_replace('/\s+/', ' ', trim($area));
        // Reuse the existing spelling of an area that differs only in letter case.
        return $this->knownAreas()->first(fn ($known) => mb_strtolower($known) === mb_strtolower($area)) ?? $area;
    }

    /** Approved riders of this partner with their current load and today's results. */
    private function riderWorkload(): Collection
    {
        $provider = $this->logisticsProvider();
        if (! $provider) {
            return collect();
        }
        $byRider = Schema::hasColumn('shipments', 'rider_id') ? 'rider_id' : 'courier_id';
        $count = fn (array $stages, ?Carbon $since = null) => ShipmentWorkflow::whereStage($this->parcels(), $stages)
            ->whereNotNull($byRider)
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>=', $since))
            ->selectRaw("{$byRider} as rider_key, count(*) as total")->groupBy($byRider)->pluck('total', 'rider_key');
        $today = now('Asia/Manila')->startOfDay()->utc();
        $assigned = $count(['assigned']);
        $out = $count(['out_for_delivery']);
        $delivered = $count(['delivered'], $today);
        $failed = $count(['failed']);

        return $provider->riders()->where('status', 'approved')->with('user')->get()
            ->filter(fn (Rider $rider) => $rider->user)
            ->map(function (Rider $rider) use ($byRider, $assigned, $out, $delivered, $failed) {
                $key = $byRider === 'rider_id' ? $rider->id : $rider->user_id;

                return [
                    'rider' => $rider,
                    'assigned' => (int) ($assigned[$key] ?? 0),
                    'out' => (int) ($out[$key] ?? 0),
                    'delivered' => (int) ($delivered[$key] ?? 0),
                    'failed' => (int) ($failed[$key] ?? 0),
                ];
            })
            ->sortByDesc(fn ($row) => $row['assigned'] + $row['out'])->values();
    }

    private function reportRange(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'item' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', Rule::in(array_keys(ShipmentWorkflow::STAGES))],
            'rider' => ['nullable', 'integer'],
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $from = isset($filters['from']) ? Carbon::parse($filters['from'], 'Asia/Manila')->startOfDay()->utc() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'], 'Asia/Manila')->endOfDay()->utc() : null;

        return [$from, $to, $filters];
    }

    private function reportQuery(?Carbon $from, ?Carbon $to, array $filters = []): Builder
    {
        $item = trim((string) ($filters['item'] ?? ''));
        $stage = $filters['stage'] ?? null;
        $riderId = $filters['rider'] ?? null;

        return $this->parcels()
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
            ->when($stage, fn (Builder $q) => ShipmentWorkflow::whereStage($q, [$stage]))
            ->when($riderId, fn (Builder $q) => Schema::hasColumn('shipments', 'rider_id') ? $q->where('rider_id', $riderId) : $q->where('courier_id', $riderId))
            ->when($item !== '', function (Builder $q) use ($item) {
                $like = '%'.$item.'%';
                $q->where(function (Builder $sub) use ($like) {
                    if (Schema::hasColumn('shipments', 'order_id')) {
                        $sub->whereHas('order.items', fn ($items) => $items->where('product_name', 'like', $like));
                    }
                    if (Schema::hasColumn('shipments', 'seller_order_id')) {
                        $sub->orWhereHas('sellerOrder.items', fn ($items) => $items->where('product_name', 'like', $like));
                    }
                });
            });
    }

    private function buildReport(?Carbon $from, ?Carbon $to, array $filters = []): array
    {
        $byStage = collect(ShipmentWorkflow::STAGES)
            ->reject(fn ($label, $stage) => in_array($stage, ['confirmed', 'sorted'], true) && ! ShipmentWorkflow::hasHubSteps())
            ->map(fn ($label, $stage) => [
                'label' => $label,
                'total' => ShipmentWorkflow::whereStage($this->reportQuery($from, $to, $filters), [$stage])->count(),
            ]);
        $total = $this->reportQuery($from, $to, $filters)->count();
        $delivered = $byStage['delivered']['total'];
        $finished = $delivered + $byStage['failed']['total'] + $byStage['returned']['total'];

        // Per rider and per area, from the parcels themselves.
        $parcels = $this->withParcelDetails($this->reportQuery($from, $to, $filters))->get();
        $byRider = $parcels->filter(fn (Shipment $shipment) => ShipmentWorkflow::riderOf($shipment))
            ->groupBy(fn (Shipment $shipment) => ParcelView::for($shipment)['rider'] ?? 'Unknown rider')
            ->map(fn (Collection $group, $name) => $this->tally($name, $group))
            ->sortByDesc('total')->values();
        $byArea = $parcels
            ->groupBy(fn (Shipment $shipment) => $shipment->sort_area ?: (ShipmentWorkflow::suggestedArea($shipment) ?: 'Unknown area'))
            ->map(fn (Collection $group, $name) => $this->tally($name, $group))
            ->sortByDesc('total')->values();

        return [
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $byStage['failed']['total'] + $byStage['returned']['total'],
            'active' => $byStage->only(ShipmentWorkflow::ACTIVE)->except('failed')->sum('total'),
            // Success rate over parcels that reached an end (delivered, failed or returned), not ones still moving.
            'successRate' => $finished ? round($delivered / $finished * 100) : 0,
            'byStage' => $byStage,
            'byRider' => $byRider,
            'byArea' => $byArea,
        ];
    }

    private function tally(string $name, Collection $group): array
    {
        $stages = $group->map(fn (Shipment $shipment) => ShipmentWorkflow::stageOf($shipment));
        $delivered = $stages->filter(fn ($stage) => $stage === 'delivered')->count();
        $failed = $stages->filter(fn ($stage) => in_array($stage, ['failed', 'returned'], true))->count();

        return [
            'name' => $name,
            'total' => $group->count(),
            'delivered' => $delivered,
            'failed' => $failed,
            'rate' => $delivered + $failed ? round($delivered / ($delivered + $failed) * 100) : null,
        ];
    }

    private function search(Builder $query, string $search): Builder
    {
        return $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $match) => $this->matchTracking($match, $search)));
    }

    /** Riders of this partner who are approved, active and not paused. */
    private function availableRiders(): Collection
    {
        return Rider::query()
            ->with('user')
            ->where('logistics_provider_id', $this->logisticsProvider()?->id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->whereHas('user', fn (Builder $user) => $user->where('status', 'active'))
            ->orderBy('id')
            ->get();
    }

    private function withoutRider(Builder $query): void
    {
        if (Schema::hasColumn('shipments', 'rider_id')) {
            $query->whereNull('rider_id');
        }
        if (Schema::hasColumn('shipments', 'courier_id')) {
            $query->whereNull('courier_id');
        }
    }

    private function inArea(Builder $query, string $area): void
    {
        $like = '%'.$area.'%';
        $matchOrder = fn (Builder $order) => Order::usesLegacySchema()
            ? $order->where('shipping_address', 'like', $like)
            : $order->where('shipping_address->city', 'like', $like)->orWhere('shipping_address->province', 'like', $like);
        $query->where(function (Builder $parcels) use ($matchOrder) {
            if (Schema::hasColumn('shipments', 'order_id')) {
                $parcels->whereHas('order', $matchOrder);
            }
            if (Schema::hasColumn('shipments', 'seller_order_id')) {
                $parcels->orWhereHas('sellerOrder.order', $matchOrder);
            }
        });
    }

    /** Tracking number, or (when not exact) an order number too. */
    private function matchTracking(Builder $query, string $value, bool $exact = false): void
    {
        $operator = $exact ? '=' : 'like';
        $term = $exact ? $value : '%'.$value.'%';
        $first = true;
        foreach (['tracking_number', 'tracking_code'] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                $query->{$first ? 'where' : 'orWhere'}($column, $operator, $term);
                $first = false;
            }
        }
        $orderNumber = Order::usesLegacySchema() ? 'order_number' : 'reference';
        $byOrder = fn (Builder $order) => $order->where($orderNumber, $operator, $term);
        if (Schema::hasColumn('shipments', 'order_id')) {
            $query->orWhereHas('order', $byOrder);
        }
        if (Schema::hasColumn('shipments', 'seller_order_id')) {
            $query->orWhereHas('sellerOrder.order', $byOrder);
        }
    }

    private function tracking(Shipment $shipment): string
    {
        return $shipment->tracking_number ?? $shipment->tracking_code ?? '#'.$shipment->id;
    }
}
