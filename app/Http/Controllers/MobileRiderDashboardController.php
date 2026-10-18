<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRiderRequest;
use App\Models\Announcement;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderDeliveries;
use App\Services\ShipmentWorkflow;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The rider app's dashboard and delivery actions. Parcels reach riders the
 * same way as on the website: the logistics partner assigns them, and the
 * rider takes them out for delivery (RiderDeliveries, on ShipmentWorkflow).
 */
class MobileRiderDashboardController extends Controller
{
    /** Days before expiry when the app starts warning about the license. */
    private const LICENSE_WARNING_DAYS = 30;

    /** Everything a delivery card shows: the order, its items, and where to pick it up. */
    private const RELATIONS = ['order.items', 'order.seller.sellerProfile', 'order.seller.addresses'];

    public function __construct(private readonly RiderDeliveries $deliveries) {}

    public function dashboard(Request $request): JsonResponse
    {
        $rider = $request->user();
        $completed = $this->deliveries->completed($rider)->with(self::RELATIONS)->get();
        $today = now(RegisterRiderRequest::TIMEZONE)->startOfDay();
        $sellerManaged = RiderDeliveries::sellerManaged();

        return response()->json([
            'rider' => $this->riderPayload($rider),
            'announcements' => MobilePayload::announcements(Announcement::for('rider')),
            // While sellers deliver their own parcels, the app explains why there is nothing to do.
            'delivery_mode' => $sellerManaged ? 'seller_managed' : 'logistics',
            'delivery_mode_message' => $sellerManaged
                ? 'Sellers are delivering their own orders for now. Deliveries will appear here once your logistics partner starts assigning parcels.'
                : null,
            'stats' => [
                // Riders no longer pick parcels themselves; the logistics partner assigns them.
                'available' => 0,
                'active' => $this->deliveries->active($rider)->count(),
                'completed' => $completed->count(),
                'completed_today' => $completed->filter(fn (Shipment $s) => $s->delivered_at?->gte($today))->count(),
                // Riders earn the order's delivery fee for each completed delivery.
                'earnings_today' => round($completed->filter(fn (Shipment $s) => $s->delivered_at?->gte($today))
                    ->sum(fn (Shipment $s) => (float) $s->order?->shipping_fee), 2),
                'earnings_total' => round($completed->sum(fn (Shipment $s) => (float) $s->order?->shipping_fee), 2),
            ],
            'active' => $this->deliveries->active($rider)->with(self::RELATIONS)->latest('updated_at')->get()
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment))->values(),
            'available' => [],
            'recent' => $completed->sortByDesc('delivered_at')->take(5)
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment))->values(),
        ]);
    }

    /** Periods the history and earnings screens filter by. */
    private const PERIODS = ['today', 'week', 'month', 'all'];

    /**
     * Completed deliveries, newest first, 20 per page, with the count and
     * delivery fees for the chosen period (today, week, month, or all).
     */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:'.implode(',', self::PERIODS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = $data['period'] ?? 'all';
        $query = $this->deliveredIn($request->user(), $period);

        $page = (clone $query)->with(self::RELATIONS)->orderByDesc('delivered_at')->paginate(20);
        $all = (clone $query)->with('order')->get();

        return response()->json([
            'period' => $period,
            'summary' => ['deliveries' => $all->count(), 'earnings' => $this->fees($all)],
            'deliveries' => collect($page->items())->map(fn (Shipment $s) => $this->shipmentPayload($s))->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /** Delivery-fee earnings for today, this week, this month, all time, and the last 7 days. */
    public function earnings(Request $request): JsonResponse
    {
        $rider = $request->user();
        $completed = $this->deliveries->completed($rider)->with('order')->get();
        $today = now(RegisterRiderRequest::TIMEZONE)->startOfDay();

        $days = collect(range(6, 0))->map(function (int $daysAgo) use ($completed, $today) {
            $start = $today->copy()->subDays($daysAgo);
            $inDay = $completed->filter(fn (Shipment $s) => $s->delivered_at && $s->delivered_at->gte($start) && $s->delivered_at->lt($start->copy()->addDay()));

            return ['date' => $start->toDateString(), 'deliveries' => $inDay->count(), 'earnings' => $this->fees($inDay)];
        });

        return response()->json([
            'periods' => collect(self::PERIODS)->mapWithKeys(function (string $period) use ($rider) {
                $inPeriod = $this->deliveredIn($rider, $period)->with('order')->get();

                return [$period => ['deliveries' => $inPeriod->count(), 'earnings' => $this->fees($inPeriod)]];
            }),
            'last_7_days' => $days->values(),
            'note' => 'Riders earn the delivery fee of each order they deliver.',
        ]);
    }

    private function deliveredIn(User $rider, string $period)
    {
        $now = now(RegisterRiderRequest::TIMEZONE);
        $since = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            default => null,
        };

        return $this->deliveries->completed($rider)->when($since, fn ($query) => $query->where('delivered_at', '>=', $since->utc()));
    }

    private function fees($shipments): float
    {
        return round($shipments->sum(fn (Shipment $s) => (float) $s->order?->shipping_fee), 2);
    }

    /** Moves the rider's delivery to its next step: out for delivery, delivered, or could not deliver. */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:out_for_delivery,delivered,failed']);
        $shipment = $this->deliveries->advance($shipment, $request->user(), $data['status']);

        return response()->json([
            'message' => ShipmentWorkflow::label($data['status']).'.',
            'delivery' => $this->shipmentPayload($shipment->load(self::RELATIONS)),
        ]);
    }

    /** Where the rider collects the parcel: the seller's store and default address. */
    private function pickupPayload(?User $seller): ?array
    {
        if (! $seller) {
            return null;
        }
        $address = $seller->addresses->firstWhere('is_default', true) ?? $seller->addresses->first();

        return [
            'store_name' => $seller->sellerProfile?->store_name ?? $seller->name,
            'phone' => $address?->phone ?? $seller->phone,
            'address' => $address?->formatted(),
        ];
    }

    private function riderPayload(User $user): array
    {
        $rider = $user->rider()->with('provider')->first();
        $expires = $rider?->license_expires_on;
        $daysLeft = $expires ? (int) now(RegisterRiderRequest::TIMEZONE)->startOfDay()->diffInDays($expires, false) : null;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'gender_label' => User::GENDERS[$user->gender] ?? null,
            'address' => MobileRiderAccountController::addressPayload(
                $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first()
            ),
            'status' => $user->status,
            'approved_at' => $user->approved_at?->toIso8601String(),
            'logistics_partner' => $rider?->provider?->name,
            'vehicle_type' => $rider?->vehicle_type,
            'vehicle_label' => $rider?->vehicle_type,
            'plate_number' => $rider?->plate_no,
            'license_number' => $rider?->license_number,
            'license_expires_on' => $expires?->toDateString(),
            'license_days_left' => $daysLeft,
            'license_expiring_soon' => $daysLeft !== null && $daysLeft <= self::LICENSE_WARNING_DAYS,
        ];
    }

    /** A delivery assigned to the rider, with the steps they can take next. */
    private function shipmentPayload(Shipment $shipment): array
    {
        $order = $shipment->order;
        $stage = ShipmentWorkflow::stageOf($shipment);
        $steps = RiderDeliveries::sellerManaged() ? [] : RiderDeliveries::nextSteps($shipment);
        $cashOnDelivery = $order && $order->payment_method === 'cod';

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'status' => $stage,
            'status_label' => ShipmentWorkflow::label($stage),
            // The main button, then any other choice (e.g. "Could not deliver").
            'next_status' => array_key_first($steps),
            'next_action_label' => $steps ? reset($steps) : null,
            'actions' => collect($steps)->map(fn (string $label, string $status) => ['status' => $status, 'label' => $label])->values(),
            'order_number' => $order?->order_number,
            'shipping_address' => $order?->shipping_address,
            'recipient_name' => $order?->recipient_name,
            'recipient_phone' => $order?->phone,
            'notes' => $order?->notes,
            'item_count' => (int) ($order?->items->sum('quantity') ?? 0),
            'items' => $order ? $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
            ])->values() : [],
            'pickup' => $this->pickupPayload($order?->seller),
            'payment_method' => $order?->payment_method,
            'cash_to_collect' => $cashOnDelivery ? round((float) $order->total, 2) : 0.0,
            'delivery_fee' => round((float) ($order?->shipping_fee ?? 0), 2),
            'picked_up_at' => $shipment->picked_up_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'created_at' => $shipment->created_at?->toIso8601String(),
            'chat_open' => $shipment->chatIsOpen(),
            'unread_messages' => $shipment->chatIsOpen() ? $shipment->unreadMessagesFor(rider: true) : 0,
        ];
    }
}
