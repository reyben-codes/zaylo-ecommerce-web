<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SortingCenterController extends Controller
{
    private const STATUSES = ['unassigned', 'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'];

    public function dashboard(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $query = Shipment::query()->with(['order', 'sellerOrder.order', 'provider', 'rider']);

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $this->applyShipmentSearch($query, $search, true);
        }

        $shipments = $query->latest()->paginate(15)->withQueryString();

        return view('sorting-center.dashboard', [
            'shipments' => $shipments,
            'status' => $status,
            'search' => $search,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn (string $value) => [$value => Shipment::where('status', $value)->count()]),
        ]);
    }

    public function riders()
    {
        $applicationQuery = User::query()->where('status', 'pending');
        if (Schema::hasColumn('users', 'role')) {
            $applicationQuery->whereIn('role', ['courier', 'rider']);
        } else {
            $applicationQuery->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['courier', 'rider']));
        }

        return view('sorting-center.riders', [
            'applications' => $applicationQuery->latest()->paginate(10, ['*'], 'applications_page'),
            'riders' => Rider::query()->with(['user', 'provider'])->latest()->paginate(10, ['*'], 'riders_page'),
        ]);
    }

    public function updateApplication(Request $request, User $user)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,disapproved']]);
        $approved = $data['decision'] === 'approved';
        DB::transaction(function () use ($user, $request, $approved): void {
            $applicant = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($applicant->role, ['courier', 'rider'], true), 404);
            abort_unless($applicant->status === 'pending', 422, 'This application is no longer pending.');
            $applicant->update([
                'status' => $approved ? 'active' : 'suspended',
                'approved_at' => $approved ? now() : null,
                'approved_by' => $approved ? $request->user()->id : null,
            ]);
        });

        return back()->with('status', $approved ? 'Rider application approved.' : 'Rider application disapproved.');
    }

    public function updateRiderStatus(Request $request, Rider $rider)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $rider->update(['is_active' => $data['is_active']]);

        return back()->with('status', 'Rider account status updated.');
    }

    public function pickups()
    {
        return view('sorting-center.pickups');
    }

    public function incoming(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $shipments = Shipment::query()
            ->with(['order', 'sellerOrder.order', 'provider'])
            ->whereIn('status', ['ready', 'unassigned'])
            ->when($search !== '', fn ($query) => $this->applyShipmentSearch($query, $search))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sorting-center.incoming', compact('shipments', 'search'));
    }

    public function sorting(Request $request)
    {
        $tracking = trim($request->string('tracking')->toString());
        $shipment = null;

        if ($tracking !== '') {
            $shipment = Shipment::query()
                ->with(['order', 'sellerOrder.order', 'provider', 'rider'])
                ->where(function (Builder $query) use ($tracking): void {
                    if (Schema::hasColumn('shipments', 'tracking_code')) {
                        $query->where('tracking_code', $tracking);
                    }
                    if (Schema::hasColumn('shipments', 'tracking_number')) {
                        $method = Schema::hasColumn('shipments', 'tracking_code') ? 'orWhere' : 'where';
                        $query->{$method}('tracking_number', $tracking);
                    }
                })
                ->first();
        }

        return view('sorting-center.sorting', compact('tracking', 'shipment'));
    }

    public function assignments(Request $request)
    {
        $area = trim($request->string('area')->toString());
        $shipments = Shipment::query()
            ->with(['order', 'sellerOrder.order', 'rider'])
            ->when(Schema::hasColumn('shipments', 'rider_id'), fn ($query) => $query->whereNull('rider_id'))
            ->when(Schema::hasColumn('shipments', 'courier_id'), fn ($query) => $query->whereNull('courier_id'))
            ->whereNotIn('status', ['delivered', 'failed', 'returned', 'cancelled'])
            ->when($area !== '', function (Builder $query) use ($area): void {
                $query->where(function (Builder $parcels) use ($area): void {
                    $matchArea = fn (Builder $order) => Order::usesLegacySchema()
                        ? $order->where('shipping_address', 'like', '%'.$area.'%')->orWhere('recipient_name', 'like', '%'.$area.'%')
                        : $order->where('shipping_address->city', 'like', '%'.$area.'%')
                            ->orWhere('shipping_address->province', 'like', '%'.$area.'%')
                            ->orWhere('recipient_name', 'like', '%'.$area.'%');
                    if (Schema::hasColumn('shipments', 'order_id')) {
                        $parcels->whereHas('order', $matchArea);
                    }
                    if (Schema::hasColumn('shipments', 'seller_order_id')) {
                        $method = Schema::hasColumn('shipments', 'order_id') ? 'orWhereHas' : 'whereHas';
                        $parcels->{$method}('sellerOrder.order', $matchArea);
                    }
                });
            })
            ->latest()
            ->paginate(15);
        $riders = Rider::query()
            ->with('user')
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->get();

        return view('sorting-center.assignments', compact('shipments', 'riders', 'area'));
    }

    public function assignRider(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['rider_id' => ['required', 'integer', 'exists:riders,id']]);
        $rider = Rider::query()
            ->whereKey($data['rider_id'])
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();

        $historyUnavailable = false;
        DB::transaction(function () use ($shipment, $rider, $request, &$historyUnavailable): void {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $hasCourierAssignment = Schema::hasColumn('shipments', 'courier_id') && $locked->courier_id;
            if ($locked->rider_id || $hasCourierAssignment || in_array($locked->status, ['delivered', 'failed', 'returned', 'cancelled'], true)) {
                throw ValidationException::withMessages(['shipment' => 'This parcel is no longer available for assignment.']);
            }

            $locked->update(['rider_id' => $rider->id, 'status' => 'assigned']);
            $order = $locked->order ?: $locked->sellerOrder?->order;
            if ($order) {
                if (Schema::hasTable('order_status_histories')) {
                    $order->statusHistory()->create([
                        'changed_by' => $request->user()->id,
                        'status' => 'assigned',
                        'note' => 'Shipment assigned to a rider by sorting center.',
                    ]);
                } else {
                    $historyUnavailable = true;
                }
            }
        });

        return back()->with('status', $historyUnavailable
            ? 'Rider assigned to parcel. Order status history is not enabled in this installation.'
            : 'Rider assigned to parcel.');
    }

    public function monitoring(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $shipments = Shipment::query()
            ->with(['order', 'sellerOrder.order', 'provider', 'rider.user'])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $this->applyShipmentSearch($query, $search))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sorting-center.monitoring', [
            'shipments' => $shipments,
            'status' => $status,
            'search' => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    public function reports(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = Shipment::query()
            ->when($filters['from'] ?? null, fn ($builder, $from) => $builder->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($builder, $to) => $builder->whereDate('created_at', '<=', $to));

        return view('sorting-center.reports', [
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'total' => (clone $query)->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
            'inProgress' => (clone $query)->whereIn('status', ['assigned', 'picked_up', 'in_transit', 'out_for_delivery'])->count(),
            'byStatus' => (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->get(),
        ]);
    }

    public function chat()
    {
        return view('sorting-center.chat');
    }

    public function account()
    {
        return view('sorting-center.account', ['user' => auth()->user()]);
    }

    private function applyShipmentSearch(Builder $query, string $search, bool $exactTracking = false): void
    {
        $query->where(function (Builder $nested) use ($search, $exactTracking): void {
            $operator = $exactTracking ? '=' : 'like';
            $value = $exactTracking ? $search : '%'.$search.'%';
            if (Schema::hasColumn('shipments', 'tracking_code')) {
                $nested->where('tracking_code', $operator, $value);
            }
            if (Schema::hasColumn('shipments', 'tracking_number')) {
                $method = Schema::hasColumn('shipments', 'tracking_code') ? 'orWhere' : 'where';
                $nested->{$method}('tracking_number', $operator, $value);
            }

            if (! $exactTracking) {
                $orderNumberColumn = Order::usesLegacySchema() ? 'order_number' : 'reference';
                if (Schema::hasColumn('shipments', 'order_id')) {
                    $nested->orWhereHas('order', fn (Builder $order) => $order->where($orderNumberColumn, 'like', $value));
                }
                if (Schema::hasColumn('shipments', 'seller_order_id')) {
                    $nested->orWhereHas('sellerOrder.order', fn (Builder $order) => $order->where($orderNumberColumn, 'like', $value));
                }
            }
        });
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', self::STATUSES)]]);
        $historyUnavailable = false;

        DB::transaction(function () use ($shipment, $data, &$historyUnavailable): void {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $data['status']) {
                return;
            }

            $locked->update(['status' => $data['status']]);
            $order = $locked->order ?: $locked->sellerOrder?->order;
            if ($order) {
                if (Schema::hasTable('order_status_histories')) {
                    $order->statusHistory()->create([
                        'changed_by' => auth()->id(),
                        'status' => $data['status'],
                        'note' => 'Shipment status updated by sorting center.',
                    ]);
                } else {
                    $historyUnavailable = true;
                }
            }
        });

        return back()->with('status', $historyUnavailable
            ? 'Shipment status updated. Order status history is not enabled in this installation.'
            : 'Shipment status updated.');
    }
}