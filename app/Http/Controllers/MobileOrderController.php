<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CheckoutService;
use App\Services\OrderReceipts;
use App\Services\ProductReviews;
use App\Support\MobilePayload;
use App\Support\OrderParties;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * A buyer's orders in the mobile app: the list by status, one order with its history, cancelling, confirming a
 * delivered order was received (with the rider's photo), and reviews.
 */
class MobileOrderController extends Controller
{
    public function index(Request $request, OrderReceipts $receipts): JsonResponse
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['all', ...array_keys(MobilePayload::STATUS_GROUPS)])],
        ])['status'] ?? 'all';
        $receipts->autoConfirmDue();

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

    public function show(Request $request, Order $order, OrderReceipts $receipts): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $receipts->autoConfirmDue();
        $order->refresh();

        return response()->json(['order' => $this->detailed($order)]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $checkout->cancel($order, $request->user());

        return response()->json([
            'message' => 'Order cancelled and stock restored.',
            'order' => $this->detailed($order->fresh()),
        ]);
    }

    /** The buyer received a delivered order, which completes it. */
    public function received(Request $request, Order $order, OrderReceipts $receipts): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $receipts->confirm($order, $request->user());

        return response()->json([
            'message' => 'Thank you! Your order is complete. You can now rate your items.',
            'order' => $this->detailed($order->fresh()),
        ]);
    }

    /** The rider's photo of the delivered parcel. */
    public function deliveryPhoto(Request $request, Order $order)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $path = $order->deliveryShipment()?->delivery_proof_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Rates a delivered item, with up to three photos, like the website's review form. */
    public function review(Request $request, OrderItem $orderItem, ProductReviews $reviews): JsonResponse
    {
        $reviews->authorize($request->user(), $orderItem);
        $data = $request->validate(ProductReviews::rules());
        $reviews->publish($request->user(), $orderItem, (int) $data['rating'], $data['comment'] ?? null, $request->file('photos', []));

        return response()->json(['message' => 'Thank you! Your product review has been published.'], 201);
    }

    private function detailed(Order $order): array
    {
        $order->load(array_filter([
            'items.product.images', 'items.review', 'payment',
            Order::usesLegacySchema() ? 'shipment' : null,
            ...(Schema::hasTable('order_status_histories') ? ['statusHistory' => fn ($query) => $query->oldest()] : []),
            // Complaints arrive with the admin modules migration.
            Schema::hasTable('dispute_messages') ? 'disputes' : null,
        ]));

        return MobilePayload::order($order, true);
    }
}
