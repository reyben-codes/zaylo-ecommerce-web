<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Services\ProductReviews;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviews $reviews) {}

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
        $this->reviews->publish($request->user(), $orderItem, $data['rating'], $data['comment'] ?? null, $request->file('photos', []));
        return redirect()->route('buyer.orders')->with('status', 'Thank you! Your product review has been published.');
    }
}
