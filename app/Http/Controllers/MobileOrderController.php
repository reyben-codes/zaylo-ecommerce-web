<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CheckoutService;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['all', ...array_keys(MobilePayload::STATUS_GROUPS)])],
        ])['status'] ?? 'all';

        $statusCounts = $request->user()->orders()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => (int) $statusCounts->sum()];
        foreach (MobilePayload::STATUS_GROUPS as $group => $statuses) {
            $counts[$group] = collect($statuses)->sum(fn ($status) => (int) $statusCounts->get($status, 0));
        }

        $orders = $request->user()->orders()
            ->with(['items.product.images'])
            ->when($filter !== 'all', fn ($query) => $query->whereIn('status', MobilePayload::STATUS_GROUPS[$filter]))
            ->orderByDesc('placed_at')
            ->paginate(10);

        return response()->json([
            'counts' => $counts,
            'orders' => $orders->through(fn (Order $order) => MobilePayload::order($order)),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['order' => $this->detailed($order)]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $checkout->cancel($order, $request->user());

        return response()->json([
            'message' => 'Order cancelled and stock restored.',
            'order' => $this->detailed($order->fresh()),
        ]);
    }

    private function detailed(Order $order): array
    {
        $order->load(['items.product.images', 'payment', 'shipment', 'statusHistory' => fn ($query) => $query->oldest()]);

        return MobilePayload::order($order, true);
    }
}
