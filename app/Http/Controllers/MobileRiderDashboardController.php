<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderDeliveries;
use App\Support\MobilePayload;
use App\Support\ParcelView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The rider app's dashboard, history, earnings, reports and delivery actions. Parcels come from the rider's
 * logistics partner, which assigns them at its hub; the rider accepts or declines each one, and only the rider
 * takes it out, delivers it, or reports a failed attempt with the reason.
 */
class MobileRiderDashboardController extends Controller
{
    private const TIMEZONE = 'Asia/Manila';

    /** Days before expiry when the app starts warning about the license. */
    private const LICENSE_WARNING_DAYS = 30;

    private const PERIODS = ['today', 'week', 'month', 'all'];

    public function __construct(private readonly RiderDeliveries $deliveries) {}

    public function dashboard(Request $request): JsonResponse
    {
        $rider = $request->user();
        $today = now(self::TIMEZONE)->startOfDay();
        $completed = $this->deliveries->completed($rider)->with($this->relations())->get();
        $completedToday = $completed->filter(fn (Shipment $s) => RiderDeliveries::deliveredAt($s)?->gte($today));
        $active = $this->deliveries->active($rider)->with($this->relations())->latest('updated_at')->get()
            // New assignments waiting for an answer come first.
            ->sortByDesc(fn (Shipment $s) => RiderDeliveries::needsAcceptance($s))->values();

        return response()->json([
            'rider' => $this->riderPayload($rider),
            'announcements' => MobilePayload::announcements(Announcement::for('rider')),
            // The partner assigns parcels here; the app shows this only while sellers deliver their own orders.
            'delivery_mode_message' => null,
            'stats' => [
                // The logistics partner assigns parcels, so nothing waits to be claimed.
                'available' => 0,
                'active' => $active->count(),
                'awaiting_acceptance' => $active->filter(fn (Shipment $s) => RiderDeliveries::needsAcceptance($s))->count(),
                'completed' => $completed->count(),
                'completed_today' => $completedToday->count(),
                'earnings_today' => $this->fees($completedToday),
                'earnings_total' => $this->fees($completed),
            ],
            'active' => $active->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment))->values(),
            'available' => [],
            'recent' => $completed->sortByDesc(fn (Shipment $s) => RiderDeliveries::deliveredAt($s))->take(5)
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment))->values(),
            'note' => 'Your logistics partner assigns deliveries to you at the hub. Accept or decline each one.',
            'reasons' => self::reasons(),
        ]);
    }

    /** The reasons a rider picks from when declining an assignment or reporting a failed attempt. */
    public static function reasons(): array
    {
        $list = fn (array $reasons) => collect($reasons)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values();

        return ['decline' => $list(RiderDeliveries::DECLINE_REASONS), 'failed' => $list(RiderDeliveries::FAILED_REASONS)];
    }

    /** One of the rider's parcels, with everything they did with it. */
    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        abort_unless($this->deliveries->owns($shipment, $request->user()), 404);

        return response()->json(['delivery' => $this->shipmentPayload($shipment->load($this->relations()), withTimeline: true)]);
    }

    /** The rider takes a new assignment. */
    public function accept(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment = $this->deliveries->accept($shipment, $request->user());

        return response()->json([
            'message' => 'Delivery accepted. Pick the parcel up at the hub, then scan it to start.',
            'delivery' => $this->shipmentPayload($shipment->load($this->relations())),
        ]);
    }

    /** The rider turns a new assignment down; the parcel goes back to the hub for another rider. */
    public function decline(Request $request, Shipment $shipment): JsonResponse
    {
        abort_unless($this->deliveries->owns($shipment, $request->user()), 403, 'This delivery is assigned to another rider.');
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(RiderDeliveries::DECLINE_REASONS))],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,other'],
        ], [
            'reason.required' => 'Choose why you are declining this delivery.',
            'note.required_if' => 'Tell your logistics partner why you are declining.',
        ]);
        $this->deliveries->decline($shipment, $request->user(), $data['reason'], $data['note'] ?? null);

        return response()->json(['message' => 'Delivery declined. Your logistics partner will assign it to another rider.']);
    }

    /** Totals for a period: assignments, deliveries, failed attempts and their reasons, cash and fees. */
    public function reports(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', 'in:'.implode(',', self::PERIODS)]]);
        $period = $data['period'] ?? 'week';

        return response()->json(['period' => $period] + $this->deliveries->report($request->user(), $this->since($period), self::TIMEZONE));
    }

    /** Delivered parcels, newest first, 20 per page, with the count and fees for the period. */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', self::PERIODS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = $data['period'] ?? 'all';
        $query = $this->deliveries->completedSince($request->user(), $this->since($period));

        $page = (clone $query)->with($this->relations())->orderByDesc(RiderDeliveries::deliveredColumn())->paginate(20);
        $all = (clone $query)->with($this->relations())->get();

        return response()->json([
            'period' => $period,
            'summary' => ['deliveries' => $all->count(), 'earnings' => $this->fees($all)],
            'deliveries' => collect($page->items())->map(fn (Shipment $s) => $this->shipmentPayload($s))->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /** Delivery-fee totals for today, this week, this month, all time, and the last 7 days. */
    public function earnings(Request $request): JsonResponse
    {
        $rider = $request->user();
        $completed = $this->deliveries->completed($rider)->with($this->relations())->get();
        $today = now(self::TIMEZONE)->startOfDay();

        $days = collect(range(6, 0))->map(function (int $daysAgo) use ($completed, $today) {
            $start = $today->copy()->subDays($daysAgo);
            $inDay = $completed->filter(function (Shipment $s) use ($start) {
                $at = RiderDeliveries::deliveredAt($s);

                return $at && $at->gte($start) && $at->lt($start->copy()->addDay());
            });

            return ['date' => $start->toDateString(), 'deliveries' => $inDay->count(), 'earnings' => $this->fees($inDay)];
        });

        return response()->json([
            'periods' => collect(self::PERIODS)->mapWithKeys(function (string $period) use ($completed) {
                $since = $this->since($period);
                $inPeriod = $since ? $completed->filter(fn (Shipment $s) => RiderDeliveries::deliveredAt($s)?->gte($since)) : $completed;

                return [$period => ['deliveries' => $inPeriod->count(), 'earnings' => $this->fees($inPeriod)]];
            }),
            'last_7_days' => $days->values(),
            'note' => 'Earnings show the delivery fee of each parcel you delivered. Your logistics partner sets how riders are paid.',
        ]);
    }

    /** Riders no longer claim parcels: the logistics partner assigns them. Kept so older app builds get a clear answer. */
    public function claim(): JsonResponse
    {
        return response()->json([
            'message' => 'Your logistics partner assigns deliveries to you at the hub. Assigned parcels appear under Active deliveries.',
            'code' => 'assigned_by_partner',
        ], 409);
    }

    /**
     * The rider scanned a parcel label: the parcel goes out for delivery, or is delivered if it already was out.
     * The buyer (and on delivery, the seller) is notified.
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:100']);
        [$shipment, $step] = $this->deliveries->scan($request->user(), $data['code']);

        return response()->json([
            'message' => $step === 'delivered'
                ? 'Delivered. The buyer and seller were notified.'
                : 'Out for delivery. The buyer was told their parcel is on its way.',
            'step' => $step,
            'delivery' => $this->shipmentPayload($shipment->load($this->relations())),
        ]);
    }

    /**
     * Reports that a delivery could not be made, with the reason (e.g. the buyer cancelled at the door); the
     * buyer and seller are told. Starting and finishing a delivery go through scan().
     */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        // Another rider's parcel is refused before its fields are checked.
        abort_unless($this->deliveries->owns($shipment, $request->user()), 403, 'This delivery is assigned to another rider.');
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(RiderDeliveries::ACTION_LABELS))],
            'reason' => ['exclude_unless:status,failed', 'required', 'in:'.implode(',', array_keys(RiderDeliveries::FAILED_REASONS))],
            'note' => ['exclude_unless:status,failed', 'nullable', 'string', 'max:500', 'required_if:reason,other'],
        ], [
            'reason.required' => 'Choose why the parcel could not be delivered.',
            'note.required_if' => 'Describe what happened.',
        ]);
        $shipment = $this->deliveries->advance($shipment, $request->user(), $data['status'], reason: $data['reason'] ?? null, note: $data['note'] ?? null);

        return response()->json([
            'message' => match ($data['status']) {
                'out_for_delivery' => 'Delivery started. Drive safely!',
                'delivered' => 'Marked as delivered.',
                default => 'Marked as not delivered. The buyer and seller were told why, and your logistics partner will arrange another attempt or a return.',
            },
            'delivery' => $this->shipmentPayload($shipment->load($this->relations())),
        ]);
    }

    private function since(string $period): ?\Illuminate\Support\Carbon
    {
        $now = now(self::TIMEZONE);

        return match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            default => null,
        };
    }

    private function fees(Collection $shipments): float
    {
        return round($shipments->sum(fn (Shipment $s) => RiderDeliveries::amounts($s)['delivery_fee']), 2);
    }

    /** What each delivery card needs, on whichever schema this database uses. */
    private function relations(): array
    {
        return array_values(array_filter([
            Schema::hasColumn('shipments', 'order_id') ? 'order.items' : null,
            Schema::hasColumn('shipments', 'order_id') ? 'order.seller' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.order' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.seller' : null,
            Schema::hasColumn('shipments', 'seller_order_id') ? 'sellerOrder.items' : null,
            Schema::hasColumn('shipments', 'logistics_provider_id') ? 'provider' : null,
            RiderDeliveries::tracksDecisions() ? 'riderLogs' : null,
        ]));
    }

    private function riderPayload(User $user): array
    {
        $rider = $user->rider;
        $expires = $rider?->license_expires_on;
        $daysLeft = $expires ? (int) now(self::TIMEZONE)->startOfDay()->diffInDays($expires, false) : null;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'gender' => $user->gender,
            'gender_label' => User::GENDERS[$user->gender] ?? null,
            'address' => MobileRiderAccountController::addressPayload(
                $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first()
            ),
            'status' => $user->status,
            'approved_at' => ($rider?->reviewed_at ?? $user->approved_at)?->toIso8601String(),
            'company' => $rider?->provider?->name,
            'logistics_partner' => $rider?->provider?->name,
            'accepting_deliveries' => (bool) ($rider?->is_active ?? true),
            'vehicle_type' => $rider?->vehicle_type ? strtolower($rider->vehicle_type) : null,
            'vehicle_label' => $rider?->vehicle_type,
            'plate_number' => $rider?->plate_no,
            'license_number' => $rider?->license_number,
            'license_expires_on' => $expires?->toDateString(),
            'license_days_left' => $daysLeft,
            'license_expiring_soon' => $daysLeft !== null && $daysLeft <= self::LICENSE_WARNING_DAYS,
        ];
    }

    /** The recipient's details are for the rider the parcel is assigned to, which is the only rider who sees it. */
    private function shipmentPayload(Shipment $shipment, bool $withTimeline = false): array
    {
        $parcel = ParcelView::for($shipment);
        $order = $shipment->order ?: $shipment->sellerOrder?->order;
        $items = $shipment->sellerOrder?->items ?? $order?->items ?? collect();
        $amounts = RiderDeliveries::amounts($shipment);
        $next = $this->deliveries->nextSteps($shipment);
        $stage = RiderDeliveries::riderStage($shipment);
        $lastFailure = $shipment->relationLoaded('riderLogs') ? $shipment->riderLogs->where('action', 'failed')->last() : null;
        // The deployed schema does not record the partner on a parcel; it is then the rider's own partner.
        $hub = $shipment->provider ?? $shipment->sellerOrder?->provider ?? request()->user()?->rider?->provider;

        return [
            'id' => $shipment->id,
            'tracking_number' => $parcel['tracking'],
            'status' => $stage,
            'status_label' => RiderDeliveries::STATUS_LABELS[$stage] ?? $parcel['label'],
            'next_status' => $next[0] ?? null,
            'next_action_label' => isset($next[0]) ? RiderDeliveries::ACTION_LABELS[$next[0]] : null,
            // Starting and finishing a delivery are taken by scanning the parcel's QR code, not a plain button.
            'next_needs_scan' => isset($next[0]) && in_array($next[0], RiderDeliveries::SCAN_STEPS, true),
            // A new assignment: the rider accepts or declines it before anything else.
            'needs_acceptance' => $stage === 'new',
            'last_failure' => $lastFailure ? [
                'reason' => RiderDeliveries::reasonLabel('failed', $lastFailure->reason),
                'note' => $lastFailure->note,
                'at' => $lastFailure->created_at?->toIso8601String(),
            ] : null,
            'other_actions' => collect(array_slice($next, 1))->map(fn ($status) => $this->actionPayload($status))->values(),
            // Every step available now; the first is the main button (next_status).
            'actions' => collect($next)->map(fn ($status) => $this->actionPayload($status))->values(),
            'scan_code' => RiderDeliveries::labelCode($shipment),
            'order_number' => $order?->order_number ?? $order?->reference,
            'shipping_address' => $parcel['destination'] === '—' ? null : $parcel['destination'],
            'area' => $parcel['area'],
            'recipient_name' => $parcel['recipient'] === '—' ? null : $parcel['recipient'],
            'recipient_phone' => $parcel['phone'] ?? (is_array($order?->shipping_address) ? data_get($order->shipping_address, 'phone') : null),
            'notes' => $order?->notes ?? $order?->note,
            'item_count' => (int) $items->sum('quantity'),
            'items' => $items->map(fn ($item) => ['name' => $item->product_name, 'quantity' => (int) $item->quantity])->values(),
            // Riders collect assigned parcels from their logistics partner's hub.
            'pickup' => $hub ? [
                'store_name' => $hub->name,
                'phone' => $hub->contact_phone,
                'address' => $hub->formatted_address ?: null,
            ] : null,
            'payment_method' => $amounts['payment_method'],
            'cash_to_collect' => $amounts['cash_to_collect'],
            'delivery_fee' => $amounts['delivery_fee'],
            'attempts' => (int) ($shipment->attempts ?? 0),
            'picked_up_at' => $shipment->picked_up_at?->toIso8601String(),
            'delivered_at' => RiderDeliveries::deliveredAt($shipment)?->toIso8601String(),
            'created_at' => $shipment->created_at?->toIso8601String(),
            // The rider–buyer chat, open while the parcel is with the rider.
            'chat_open' => $shipment->chatIsOpen(),
            'unread_messages' => $shipment->chatIsOpen() ? $shipment->unreadMessagesFor(rider: true) : 0,
        ] + ($withTimeline ? ['timeline' => RiderDeliveries::timeline($shipment, self::TIMEZONE)] : []);
    }

    private function actionPayload(string $status): array
    {
        return [
            'status' => $status,
            'label' => RiderDeliveries::ACTION_LABELS[$status],
            // Taken by scanning the parcel's QR code instead of a plain button press.
            'needs_scan' => in_array($status, RiderDeliveries::SCAN_STEPS, true),
        ];
    }
}
