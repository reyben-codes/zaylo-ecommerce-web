<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Services\ProductReviews;
use Illuminate\Http\Request;

/** The website's review form for a delivered item; the rules live in ProductReviews, shared with the mobile app. */
class ProductReviewController extends Controller
{
    public function __construct(private readonly ProductReviews $reviews) {}

    public function create(Request $request, OrderItem $orderItem)
    {
        $this->reviews->authorize($request->user(), $orderItem);
        if ($orderItem->review) return redirect()->route('buyer.orders')->with('status', 'You already reviewed this item.');
        return view('buyer.review', ['item' => $orderItem]);
    }

    public function store(Request $request, OrderItem $orderItem)
    {
        $this->reviews->authorize($request->user(), $orderItem);
        $data = $request->validate(ProductReviews::rules());
        $this->reviews->publish($request->user(), $orderItem, (int) $data['rating'], $data['comment'] ?? null, $request->file('photos', []));

        return redirect()->route('buyer.orders')->with('status', 'Thank you! Your product review has been published.');
    }
}
