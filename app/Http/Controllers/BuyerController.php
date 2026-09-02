<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function dashboard()
    {
        $flashProducts   = Product::whereNotNull('original_price')->inRandomOrder()->limit(4)->get();
        $suggestedProducts = Product::inRandomOrder()->limit(8)->get();

        return view('buyer.dashboard', [
            'user'             => auth()->user(),
            'flashProducts'    => $flashProducts,
            'suggestedProducts'=> $suggestedProducts,
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(12);

        return view('buyer.products', [
            'products' => $products,
            'category' => $request->category,
        ]);
    }

    public function orders()
    {
        return view('buyer.orders');
    }

    public function cart()
    {
        return view('buyer.cart');
    }

    public function wishlist()
    {
        return view('buyer.wishlist');
    }

    public function account()
    {
        return view('buyer.account');
    }

    public function chat()
    {
        return view('buyer.chat');
    }
}
