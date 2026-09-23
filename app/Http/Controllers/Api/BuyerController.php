<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $flashProducts = Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull('original_price')
            ->with('seller')
            ->withCount('variants')
            ->inRandomOrder()
            ->take(4)
            ->get();

        $suggestedProducts = Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('seller')
            ->withCount('variants')
            ->inRandomOrder()
            ->take(8)
            ->get();

        return response()->json([
            'user' => auth()->user(),
            'categories' => config('marketplace.browse_categories'),
            'flash_products' => $flashProducts,
            'suggested_products' => $suggestedProducts,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string'],
            'gender' => ['nullable', 'in:men,women,unisex'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,price_low,price_high'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('seller')
            ->withCount('variants');

        if (! empty($validated['category'])) {
            $category = $validated['category'];

            if ($category === 'fashion') {
                $query->where('fashion_category', $category);
            } else {
                $query->where('category', $category);
            }
        }

        if (! empty($validated['gender'])) {
            $query->where('gender', $validated['gender']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        switch ($validated['sort'] ?? 'newest') {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;

            case 'price_high':
                $query->orderBy('price', 'desc');
                break;

            default:
                $query->latest();
                break;
        }

        $products = $query->paginate($validated['per_page'] ?? 12);

        return response()->json($products);
    }

    public function shops(): JsonResponse
    {
        $shops = Shop::query()
            ->where('is_active', true)
            ->whereHas('seller', function ($query) {
                $query->where('status', 'active');
            })
            ->with('seller:id,name')
            ->withCount('products')
            ->orderByDesc('featured')
            ->latest()
            ->get();

        return response()->json([
            'shops' => $shops,
        ]);
    }

public function product(int $id): JsonResponse
{
    $product = Product::query()
        ->where('is_active', true)
        ->where('stock', '>', 0)
        ->with('seller')
        ->withCount('variants')
        ->findOrFail($id);

    return response()->json([
        'product' => $product,
    ]);
}
}