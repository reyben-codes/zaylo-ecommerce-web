<?php

namespace App\Http\Controllers;

use App\Services\BuyAgain;
use App\Support\BuyerOrderTabs;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CheckoutService;
use App\Services\OrderReceipts;
use App\Services\ParcelTracking;
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
            'status' => ['nullable', Rule::in(BuyerOrderTabs::tabs())],
        ])['status'] ?? 'all';
        $receipts->autoConfirmDue();
        app(\App\Services\GcashPayments::class)->expireDue(app(CheckoutService::class), buyerId: $request->user()->id);

        $counts = BuyerOrderTabs::counts($request->user()->orders()->getQuery());

        // The list shows Order received, Cancel, Return / Refund, Buy again and To review on each card, so it needs
        // the reviews and complaints too.
        $orders = BuyerOrderTabs::apply($request->user()->orders()->getQuery(), $filter)
            ->with(array_filter(['items.product.images', 'items.review', Schema::hasTable('dispute_messages') ? 'disputes' : null]))
            ->orderByDesc('placed_at')
            ->paginate(10);

        return response()->json([
            'counts' => $counts,
            'cancel_reasons' => collect(BuyerOrderTabs::CANCEL_REASONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
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

    /** The tracking map, polled while the buyer has it open. */
    public function tracking(Request $request, Order $order, ParcelTracking $tracking): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);

        return response()->json(['tracking' => $tracking->forBuyer($order)]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        // App builds from before reasons were asked send none; they are recorded as "other" without a note.
        $data = $request->has('reason') ? $request->validate(BuyerController::cancelRules(), BuyerController::cancelMessages()) : ['reason' => 'other'];
        $checkout->cancel($order, $request->user(), $data['reason'], filled($data['note'] ?? null) ? trim($data['note']) : null);

        return response()->json([
            'message' => 'Order cancelled and stock restored.',
            'order' => $this->detailed($order->fresh()),
        ]);
    }

    /** Puts the products of an earlier order back in the cart. */
    public function buyAgain(Request $request, Order $order, BuyAgain $buyAgain): JsonResponse
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $result = $buyAgain->add($request->user(), $order);

        return response()->json(['message' => BuyAgain::message($result)] + $result, $result['added'] ? 200 : 422);
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
