<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    public function dashboard()
    {
        $sellerId = auth()->id();
        return view('seller.dashboard', [
            'user' => auth()->user(),
            'productCount' => Product::where('seller_id', $sellerId)->count(),
            'lowStockCount' => Product::where('seller_id', $sellerId)->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'openOrderCount' => Order::where('seller_id', $sellerId)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'revenue' => Order::where('seller_id', $sellerId)->where('status', 'completed')->sum('subtotal'),
        ]);
    }

    public function products()
    {
        $products = Product::where('seller_id', auth()->id())->latest()->paginate(12);
        return view('seller.products', compact('products'));
    }

    public function storeProduct(Request $request)
    {
        $data = $this->validatedProduct($request);
        Product::create($data + ['seller_id' => auth()->id(), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Product created successfully.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $data = $this->validatedProduct($request, $product);
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
        $products = Product::where('seller_id', auth()->id())->orderBy('stock')->paginate(20);
        return view('seller.inventory', compact('products'));
    }

    public function handover() { return view('seller.handover'); }
    public function reports() { return view('seller.reports'); }
    public function account() { return view('seller.account'); }
    public function chat() { return view('seller.chat'); }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        $productId = $product?->id ?? 'NULL';
        return $request->validate([
            'name' => 'required|string|max:150',
            'sku' => "nullable|string|max:80|unique:products,sku,{$productId}",
            'category' => ['required', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => 'nullable|in:men,women,unisex',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'original_price' => 'nullable|numeric|gte:price|max:99999999.99',
            'stock' => 'required|integer|min:0|max:1000000',
            'low_stock_threshold' => 'required|integer|min:0|max:100000',
            'image_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! filter_var($value, FILTER_VALIDATE_URL) && ! Str::startsWith($value, '/images/')) {
                        $fail('The image must be a valid URL or a local path inside /images.');
                    }
                },
            ],
            'badge' => 'nullable|in:New,Sale,Best Seller',
        ]);
    }
}
