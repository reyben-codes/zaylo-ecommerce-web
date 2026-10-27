<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesLogisticsParcels;
use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Services\ShipmentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsController extends Controller
{
    use QueriesLogisticsParcels, UpdatesAccountSettings;

    public const RIDER_STATUS_LABELS = [
        'pending' => 'Pending review',
        'approved' => 'Approved',
        'suspended' => 'Suspended',
        'rejected' => 'Rejected',
    ];

    // Each review action: the rider statuses it applies to, the resulting status, and the rider's account status.
    private const RIDER_ACTIONS = [
        'approve' => ['from' => ['pending'], 'to' => 'approved', 'account' => 'active'],
        'reject' => ['from' => ['pending'], 'to' => 'rejected', 'account' => 'rejected'],
        'suspend' => ['from' => ['approved'], 'to' => 'suspended', 'account' => 'suspended'],
        'reinstate' => ['from' => ['suspended'], 'to' => 'approved', 'account' => 'active'],
    ];

    /** The sorting center at a glance: parcels at each step, riders, and what needs attention. */
    public function dashboard()
    {
        $provider = $this->provider();

        return view('logistics.dashboard', [
            'user' => auth()->user(),
            'provider' => $provider,
            'counts' => $this->riderCounts($provider),
            'parcels' => $this->parcelStageCounts(),
            'availableRiders' => $provider->riders()->where('status', 'approved')->where('is_active', true)->count(),
            'recentApplications' => $provider->riders()->where('status', 'pending')->with('user')->oldest()->limit(5)->get(),
            'recentParcels' => $this->withParcelDetails($this->parcels())
                ->where(fn ($query) => ShipmentWorkflow::whereStage($query, ShipmentWorkflow::ACTIVE))
                ->latest('updated_at')->limit(6)->get(),
        ]);
    }

    /** Pause or resume an approved rider for new assignments without suspending their account. */
    public function riderAvailability(Request $request, Rider $rider)
    {
        abort_unless((int) $rider->logistics_provider_id === (int) $this->provider()->id, 403);
        $data = $request->validate(['available' => ['required', 'boolean']]);
        abort_unless($rider->status === 'approved', 422, 'Only approved riders can be paused or resumed.');
        $rider->update(['is_active' => $data['available']]);

        return back()->with('status', $rider->user->name.($data['available'] ? ' can take new deliveries again.' : ' is paused and will not get new deliveries.'));
    }

    public function riders(Request $request)
    {
        $provider = $this->provider();
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(self::RIDER_STATUS_LABELS))]])['status'] ?? 'pending';
        $riders = $provider->riders()
            ->where('status', $status)
            ->with('user')
            ->when($status === 'pending', fn ($query) => $query->oldest(), fn ($query) => $query->latest('reviewed_at'))
            ->paginate(20)
            ->withQueryString();

        return view('logistics.riders', [
            'provider' => $provider,
            'riders' => $riders,
            'status' => $status,
            'counts' => $this->riderCounts($provider),
            'statusLabels' => self::RIDER_STATUS_LABELS,
        ]);
    }

    /** A rider's driver's license or OR/CR, only for the partner they applied to. */
    public function riderDocument(Rider $rider, string $document)
    {
        abort_unless((int) $rider->logistics_provider_id === (int) $this->provider()->id, 403);
        abort_unless(array_key_exists($document, Rider::DOCUMENTS), 404);
        $path = $rider->{$document.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, str_replace('/', '-', Rider::DOCUMENTS[$document]).'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function updateRider(Request $request, Rider $rider)
    {
        $provider = $this->provider();
        abort_unless((int) $rider->logistics_provider_id === (int) $provider->id, 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::RIDER_ACTIONS))],
            'reason' => 'nullable|string|max:500',
        ]);
        $action = self::RIDER_ACTIONS[$data['action']];

        DB::transaction(function () use ($rider, $action, $data) {
            $locked = Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $action['from'], true)) {
                throw ValidationException::withMessages(['action' => 'This rider was already updated. Refresh the page and try again.']);
            }
            $locked->update([
                'status' => $action['to'],
                'reviewed_at' => now(),
                'rejection_reason' => in_array($data['action'], ['reject', 'suspend'], true) ? ($data['reason'] ?? null) : null,
            ]);
            $locked->user->update([
                'status' => $action['account'],
                'approved_at' => $action['account'] === 'active' ? ($locked->user->approved_at ?? now()) : null,
                'approved_by' => $action['account'] === 'active' ? ($locked->user->approved_by ?? auth()->id()) : null,
            ]);
        });

        $name = $rider->user->name;
        $message = match ($data['action']) {
            'approve' => "{$name} is approved and can now sign in to accept deliveries.",
            'reject' => "{$name}'s application was rejected.",
            'suspend' => "{$name} has been suspended.",
            'reinstate' => "{$name} is active again.",
        };

        return back()->with('status', $message);
    }

    public function account()
    {
        return view('logistics.account', [
            'user' => auth()->user(),
            'provider' => $this->provider(),
        ]);
    }

    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|min:2|max:150',
            'contact_phone' => ['required', 'regex:/^09[0-9]{9}$/'],
            'service_area' => 'nullable|string|max:150',
        ], ['contact_phone.regex' => 'Enter 11 digits starting with 09.']);

        $this->provider()->update([
            'name' => $data['company_name'],
            'contact_phone' => $data['contact_phone'],
            'service_area' => $data['service_area'] ?? null,
        ]);

        return back()->with('status', 'Company details updated.');
    }

    private function provider(): LogisticsProvider
    {
        $provider = auth()->user()->logisticsProvider;
        abort_unless($provider, 404, 'No logistics company is linked to this account.');

        return $provider;
    }

    private function riderCounts(LogisticsProvider $provider): array
    {
        $counts = $provider->riders()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(array_keys(self::RIDER_STATUS_LABELS))
            ->mapWithKeys(fn (string $status) => [$status => (int) $counts->get($status, 0)])
            ->all();
    }
}
