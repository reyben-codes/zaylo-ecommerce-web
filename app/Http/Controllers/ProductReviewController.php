<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    private function authorizePurchase(Request $request, OrderItem $item): void
    {
        $order = Order::usesLegacySchema() ? $item->order : $item->sellerOrder?->order;
        abort_unless($order && (int) $order->buyer->id === (int) $request->user()->id, 403);
        $status = Order::usesLegacySchema() ? $order->status : $item->sellerOrder->status;
        abort_unless(in_array($status, ['completed', 'delivered'], true), 403, 'Reviews are available after delivery.');
        abort_unless($item->product, 404);
    }

    public function create(Request $request, OrderItem $orderItem)
    {
        $this->authorizePurchase($request, $orderItem);
        if ($orderItem->review) return redirect()->route('buyer.orders')->with('status', 'You already reviewed this item.');
        return view('buyer.review', ['item' => $orderItem]);
    }

    public function store(Request $request, OrderItem $orderItem)
    {
        $this->authorizePurchase($request, $orderItem);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $orderItem, $data, &$paths) {
                $item = OrderItem::whereKey($orderItem->id)->lockForUpdate()->firstOrFail();
                $this->authorizePurchase($request, $item);
                if ($item->review()->exists()) throw ValidationException::withMessages(['review' => 'You already reviewed this purchased item.']);
                foreach ($request->file('photos', []) as $photo) {
                    $path = $photo->store('reviews', 'public');
                    if (! $path) throw new \RuntimeException('Unable to store review photo.');
                    $paths[] = $path;
                }
                ProductReview::create([
                    'order_item_id' => $item->id, 'product_id' => $item->product_id,
                    'user_id' => $request->user()->id, 'rating' => $data['rating'],
                    'comment' => $data['comment'] ?? null, 'photos' => $paths,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
        return redirect()->route('buyer.orders')->with('status', 'Thank you! Your product review has been published.');
    }
}
