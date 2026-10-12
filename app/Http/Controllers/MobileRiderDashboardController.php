<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRiderRequest;
use App\Models\RiderApplication;
use App\Models\Shipment;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The rider app's dashboard and delivery actions. Uses the same rules as the
 * courier website through DeliveryService.
 */
class MobileRiderDashboardController extends Controller
{
    private const STATUS_LABELS = [
        'ready' => 'Ready for pickup',
        'assigned' => 'Accepted',
        'picked_up' => 'Picked up',
        'in_transit' => 'On the way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    /** What the rider taps to move a delivery to its next status. */
    private const NEXT_ACTION_LABELS = [
        'picked_up' => 'Mark as picked up',
        'in_transit' => 'Start delivery',
        'delivered' => 'Mark as delivered',
    ];

    /** Days before expiry when the app starts warning about the license. */
    private const LICENSE_WARNING_DAYS = 30;

    /** Everything a delivery card shows: the order, its items, and where to pick it up. */
    private const RELATIONS = ['order.items', 'order.seller.sellerProfile', 'order.seller.addresses'];

    public function __construct(private readonly DeliveryService $deliveries) {}

    public function dashboard(Request $request): JsonResponse
    {
        $rider = $request->user();
        $completed = $this->deliveries->completed($rider)->with(self::RELATIONS)->get();
        $today = now(RegisterRiderRequest::TIMEZONE)->startOfDay();

        return response()->json([
            'rider' => $this->riderPayload($rider),
            'stats' => [
                'available' => $this->deliveries->available()->count(),
                'active' => $this->deliveries->active($rider)->count(),
                'completed' => $completed->count(),
                'completed_today' => $completed->filter(fn (Shipment $s) => $s->delivered_at?->gte($today))->count(),
                // Riders earn the order's delivery fee for each completed delivery.
                'earnings_today' => round($completed->filter(fn (Shipment $s) => $s->delivered_at?->gte($today))
                    ->sum(fn (Shipment $s) => (float) $s->order?->shipping_fee), 2),
                'earnings_total' => round($completed->sum(fn (Shipment $s) => (float) $s->order?->shipping_fee), 2),
            ],
            'active' => $this->deliveries->active($rider)->with(self::RELATIONS)->latest('updated_at')->get()
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment, assignedToRider: true))->values(),
            'available' => $this->deliveries->available()->with(self::RELATIONS)->oldest()->limit(20)->get()
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment, assignedToRider: false))->values(),
            'recent' => $completed->sortByDesc('delivered_at')->take(5)
                ->map(fn (Shipment $shipment) => $this->shipmentPayload($shipment, assignedToRider: true))->values(),
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
            'deliveries' => collect($page->items())->map(fn (Shipment $s) => $this->shipmentPayload($s, assignedToRider: true))->values(),
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

    public function claim(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment = $this->deliveries->claim($shipment, $request->user());

        return response()->json([
            'message' => 'Delivery accepted.',
            'delivery' => $this->shipmentPayload($shipment->load(self::RELATIONS), assignedToRider: true),
        ]);
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        abort_unless($shipment->courier_id === $request->user()->id, 403, 'This delivery is assigned to another rider.');
        $data = $request->validate(['status' => 'required|in:picked_up,in_transit,delivered']);
        $shipment = $this->deliveries->advance($shipment, $request->user(), $data['status']);

        return response()->json([
            'message' => self::STATUS_LABELS[$shipment->status].'.',
            'delivery' => $this->shipmentPayload($shipment->load(self::RELATIONS), assignedToRider: true),
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

    private function riderPayload(User $rider): array
    {
        $application = $rider->riderApplication;
        $expires = $application?->license_expires_on;
        $daysLeft = $expires ? (int) now(RegisterRiderRequest::TIMEZONE)->startOfDay()->diffInDays($expires, false) : null;

        return [
            'name' => $rider->name,
            'email' => $rider->email,
            'phone' => $rider->phone,
            'gender' => $rider->gender,
            'gender_label' => User::GENDERS[$rider->gender] ?? null,
            'address' => MobileRiderAccountController::addressPayload(
                $rider->addresses()->where('is_default', true)->first() ?? $rider->addresses()->first()
            ),
            'status' => $rider->status,
            'approved_at' => $rider->approved_at?->toIso8601String(),
            'vehicle_type' => $application?->vehicle_type,
            'vehicle_label' => RiderApplication::VEHICLE_TYPES[$application?->vehicle_type] ?? null,
            'plate_number' => $application?->plate_number,
            'license_number' => $application?->license_number,
            'license_expires_on' => $expires?->toDateString(),
            'license_days_left' => $daysLeft,
            'license_expiring_soon' => $daysLeft !== null && $daysLeft <= self::LICENSE_WARNING_DAYS,
        ];
    }

    /**
     * Recipient name and phone are shared only once the rider has accepted the
     * delivery; before that the rider sees the address and amounts to decide.
     */
    private function shipmentPayload(Shipment $shipment, bool $assignedToRider): array
    {
        $order = $shipment->order;
        $next = DeliveryService::NEXT_STATUS[$shipment->status] ?? null;
        $cashOnDelivery = $order && $order->payment_method === 'cod';

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status,
            'status_label' => self::STATUS_LABELS[$shipment->status] ?? ucfirst(str_replace('_', ' ', $shipment->status)),
            'next_status' => $assignedToRider ? $next : null,
            'next_action_label' => $assignedToRider && $next ? self::NEXT_ACTION_LABELS[$next] : null,
            'order_number' => $order?->order_number,
            'shipping_address' => $order?->shipping_address,
            'recipient_name' => $assignedToRider ? $order?->recipient_name : null,
            'recipient_phone' => $assignedToRider ? $order?->phone : null,
            'notes' => $assignedToRider ? $order?->notes : null,
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
            // The buyer chat is offered only on the rider's own deliveries in progress.
            'chat_open' => $assignedToRider && $shipment->chatIsOpen(),
            'unread_messages' => $assignedToRider && $shipment->chatIsOpen() ? $shipment->unreadMessagesFor(rider: true) : 0,
        ];
    }
}
