<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function dashboard()
    {
        $legacy = Product::usesLegacySchema();
        $shop = $legacy ? auth()->user()->sellerProfile : auth()->user()->sellers()->firstOrFail();
        $sellerId = $legacy ? auth()->id() : $shop->id;
        $storeName = $legacy ? ($shop?->store_name ?? 'My Shop') : $shop->name;

        return view('seller.dashboard', [
            'user' => auth()->user(),
            'storeName' => $storeName,
            'productCount' => Product::where('seller_id', $sellerId)->count(),
            'lowStockCount' => $legacy
                ? Product::where('seller_id', $sellerId)->whereColumn('stock', '<=', 'low_stock_threshold')->count()
                : Product::where('seller_id', $sellerId)->whereHas('variants', fn ($query) => $query->whereColumn('stock', '<=', 'low_stock_threshold'))->count(),
            'openOrderCount' => $legacy
                ? Order::where('seller_id', $sellerId)->whereNotIn('status', ['completed', 'cancelled'])->count()
                : SellerOrder::where('seller_id', $sellerId)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'revenue' => $legacy
                ? Order::where('seller_id', $sellerId)->where('status', 'completed')->sum('total')
                : SellerOrder::where('seller_id', $sellerId)->where('status', 'completed')->sum('subtotal_minor') / 100,
        ]);
    }

    public function products()
    {
        $legacy = Product::usesLegacySchema();
        $shop = $legacy ? auth()->user()->sellerProfile : auth()->user()->sellers()->firstOrFail();
        $sellerId = $legacy ? auth()->id() : $shop->id;
        $relations = $legacy ? ['variants', 'images'] : ['category', 'defaultVariant', 'images'];
        $products = Product::where('seller_id', $sellerId)->with($relations)->latest()->paginate(12);
        $storeName = $legacy ? ($shop?->store_name ?? 'My Shop') : $shop->name;

        return view('seller.products', compact('products', 'storeName'));
    }

    public function storeProduct(Request $request)
    {
        $data = $this->validatedProduct($request);
        $data = $this->storeProductImage($request, $data);
        $sellerId = Product::usesLegacySchema() ? auth()->id() : auth()->user()->sellers()->firstOrFail()->id;
        Product::create($data + ['seller_id' => $sellerId, 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Product created successfully.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $data = $this->validatedProduct($request, $product);
        $data = $this->storeProductImage($request, $data, $product);
        $product->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Product updated successfully.');
    }

    public function deleteProduct(Product $product)
    {
        $this->authorize('delete', $product);
        $product->delete();

        return back()->with('status', 'Product removed. Existing order records were preserved.');
    }

    public function orders()
    {
        $orders = Order::where('seller_id', auth()->id())->with(['buyer', 'items', 'shipment'])->latest()->paginate(12);
        return view('seller.orders', compact('orders'));
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        abort_unless($order->seller_id === auth()->id(), 403);
        $data = $request->validate(['status' => 'required|in:confirmed,processing,ready_for_pickup,cancelled']);
        $allowed = [
            'placed' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['ready_for_pickup'],
        ];

        DB::transaction(function () use ($order, $data, $allowed) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'That order status transition is not allowed.']);
            }
            if ($data['status'] === 'cancelled') {
                $locked->load('items');
                foreach ($locked->items as $item) {
                    $item->product_variant_id
                        ? ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity)
                        : Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
                $locked->payment?->update(['status' => 'cancelled']);
            }
            $locked->update(['status' => $data['status']]);
            $locked->statusHistory()->create([
                'changed_by' => auth()->id(), 'status' => $data['status'], 'note' => 'Updated by seller.',
            ]);
            if ($data['status'] === 'ready_for_pickup') {
                $locked->shipment()->firstOrCreate([], [
                    'tracking_number' => 'TRK-'.Str::upper(Str::random(12)), 'status' => 'ready',
                ]);
            }
        });

        return back()->with('status', 'Order status updated.');
    }

    public function inventory()
    {
        $legacy = Product::usesLegacySchema();
        $shop = $legacy ? auth()->user()->sellerProfile : auth()->user()->sellers()->firstOrFail();
        $sellerId = $legacy ? auth()->id() : $shop->id;
        $products = Product::where('seller_id', $sellerId)->when(! $legacy, fn ($query) => $query->with('defaultVariant'))->paginate(20);
        return view('seller.inventory', compact('products'));
    }

    public function handover() { return view('seller.handover'); }
    public function reports() { return view('seller.reports'); }
    public function account()
    {
        return view('seller.account', [
            'user' => auth()->user(),
            'storeName' => Product::usesLegacySchema()
                ? (auth()->user()->sellerProfile?->store_name ?? 'My Shop')
                : (auth()->user()->sellers()->value('name') ?? 'My Shop'),
        ]);
    }
    public function chat() { return view('seller.chat'); }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        $variantId = Product::usesLegacySchema() ? 'NULL' : ($product?->defaultVariant?->id ?? 'NULL');
        return $request->validate([
            'name' => 'required|string|max:150',
            'sku' => Product::usesLegacySchema()
                ? 'nullable|string|max:80|unique:products,sku,'.($product?->id ?? 'NULL')
                : "nullable|string|max:80|unique:product_variants,sku,{$variantId}",
            'category' => ['required', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => 'nullable|in:men,women,unisex',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'original_price' => 'nullable|numeric|gte:price|max:99999999.99',
            'stock' => 'required|integer|min:0|max:1000000',
            'low_stock_threshold' => 'required|integer|min:0|max:100000',
            'weight_grams' => 'nullable|integer|min:0|max:1000000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! filter_var($value, FILTER_VALIDATE_URL) && ! Str::startsWith($value, ['/images/', '/storage/'])) {
                        $fail('The image must be a valid URL or a local product image path.');
                    }
                },
            ],
            'badge' => 'nullable|in:New,Sale,Best Seller',
        ]);
    }

    private function storeProductImage(Request $request, array $data, ?Product $product = null): array
    {
        unset($data['image']);

        if (! $request->hasFile('image')) {
            return $data;
        }

        $path = $request->file('image')->store('products/'.auth()->id(), 'public');
        $oldPath = $product && Str::startsWith((string) $product->image_url, '/storage/')
            ? Str::after($product->image_url, '/storage/')
            : null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $data['image_url'] = Storage::url($path);

        return $data;
    }
}
