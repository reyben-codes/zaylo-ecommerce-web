<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuyerController extends Controller
{
    public function dashboard()
    {
        $base = Product::where('is_active', true)->where('stock', '>', 0);
        $flashProducts = (clone $base)->whereNotNull('original_price')->inRandomOrder()->limit(4)->get();
        $suggestedProducts = (clone $base)->inRandomOrder()->limit(8)->get();

        return view('buyer.dashboard', compact('flashProducts', 'suggestedProducts') + ['user' => auth()->user()]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => 'nullable|in:men,women,unisex',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:newest,price_low,price_high',
        ]);
        $query = Product::with('seller')->where('is_active', true)->where('stock', '>', 0);

        if (! empty($validated['category'])) {
            $validated['category'] === 'fashion'
                ? $query->whereIn('category', config('marketplace.fashion_categories'))
                : $query->where('category', $validated['category']);
        }
        if (! empty($validated['gender'])) {
            $query->where('gender', $validated['gender']);
        }
        if (! empty($validated['search'])) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$validated['search'].'%')
                ->orWhere('description', 'like', '%'.$validated['search'].'%'));
        }

        match ($validated['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default => $query->latest(),
        };

        return view('buyer.products', [
            'products' => $query->paginate(12)->withQueryString(),
            'category' => $validated['category'] ?? null,
        ]);
    }

    public function showProduct(Product $product)
    {
        abort_unless($product->is_active && $product->stock > 0, 404);
        $product->load(['seller', 'variants' => fn ($query) => $query->where('is_active', true), 'images']);

        return view('buyer.product-show', compact('product'));
    }

    public function orders()
    {
        $orders = auth()->user()->orders()->with(['items', 'payment', 'shipment'])->latest()->paginate(10);

        return view('buyer.orders', compact('orders'));
    }

    public function cancelOrder(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['placed', 'confirmed'], true)) {
                throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
            }
            $lockedOrder->load(['items', 'shipment', 'payment']);
            foreach ($lockedOrder->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                } elseif ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }
            $lockedOrder->update(['status' => 'cancelled']);
            $lockedOrder->shipment?->update(['status' => 'cancelled']);
            $lockedOrder->payment?->update(['status' => 'cancelled']);
            $lockedOrder->statusHistory()->create([
                'changed_by' => auth()->id(), 'status' => 'cancelled', 'note' => 'Cancelled by buyer.',
            ]);
        });

        return back()->with('status', 'Order cancelled and stock restored.');
    }

    public function cart()
    {
        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);
        $cart->load(['items.product', 'items.variant']);

        return view('buyer.cart', [
            'cart' => $cart,
            'subtotal' => $this->cartSubtotal($cart),
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
        ]);
    }

    public function addToCart(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);
        abort_unless($product->is_active, 404);
        $variant = ! empty($validated['product_variant_id'])
            ? $product->variants()->whereKey($validated['product_variant_id'])->where('is_active', true)->firstOrFail()
            : null;
        $available = (int) ($variant?->stock ?? $product->stock);
        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);
        $itemQuery = $cart->items()->where('product_id', $product->id);
        $variant ? $itemQuery->where('product_variant_id', $variant->id) : $itemQuery->whereNull('product_variant_id');
        $item = $itemQuery->first();
        $newQuantity = ($item?->quantity ?? 0) + $validated['quantity'];

        if ($newQuantity > $available) {
            throw ValidationException::withMessages(['quantity' => "Only {$available} item(s) are available."]);
        }
        $item
            ? $item->update(['quantity' => $newQuantity])
            : $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $validated['quantity'],
            ]);

        return redirect()->route('buyer.cart')->with('status', 'Product added to your cart.');
    }

    public function updateCart(Request $request, CartItem $cartItem)
    {
        abort_unless($cartItem->cart->user_id === auth()->id(), 403);
        $validated = $request->validate(['quantity' => 'required|integer|min:1|max:99']);
        $cartItem->load(['product', 'variant']);
        if ($validated['quantity'] > $cartItem->availableStock()) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
        }
        $cartItem->update($validated);

        return back()->with('status', 'Cart updated.');
    }

    public function removeCartItem(CartItem $cartItem)
    {
        abort_unless($cartItem->cart->user_id === auth()->id(), 403);
        $cartItem->delete();

        return back()->with('status', 'Item removed from your cart.');
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            'notes' => 'nullable|string|max:500',
            'idempotency_key' => 'required|uuid',
        ]);
        if (Payment::where('idempotency_key', 'like', $validated['idempotency_key'].'%')->exists()) {
            return redirect()->route('buyer.orders')->with('status', 'This checkout was already completed.');
        }
        $cart = Cart::where('user_id', auth()->id())->with(['items.product', 'items.variant'])->first();
        if (! $cart || $cart->items->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        DB::transaction(function () use ($cart, $validated) {
            \App\Models\User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $address = Address::where('user_id', auth()->id())->whereKey($validated['address_id'])->firstOrFail();
            if (! $address->isStructured()) {
                throw ValidationException::withMessages(['address_id' => 'Please edit this saved address and select its Philippine locations before checkout.']);
            }

            foreach ($cart->items->groupBy(fn ($item) => $item->product->seller_id ?? 0) as $sellerId => $items) {
                $prepared = collect();
                foreach ($items as $item) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $variant = $item->product_variant_id
                        ? ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->firstOrFail()
                        : null;
                    $stock = (int) ($variant?->stock ?? $product->stock);
                    if (! $product->is_active || $stock < $item->quantity) {
                        throw ValidationException::withMessages(['cart' => "{$product->name} no longer has enough stock."]);
                    }
                    $prepared->push(compact('item', 'product', 'variant') + [
                        'unitPrice' => (float) ($variant?->price ?? $product->price),
                    ]);
                }

                $subtotal = $prepared->sum(fn ($row) => $row['unitPrice'] * $row['item']->quantity);
                $shippingFee = 120.00;
                $order = Order::create([
                    'order_number' => 'ZAY-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'user_id' => auth()->id(), 'seller_id' => $sellerId ?: null, 'status' => 'placed',
                    'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $subtotal + $shippingFee,
                    'payment_method' => 'cod', 'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone, 'shipping_address' => $address->formatted(),
                    'notes' => $validated['notes'] ?? null, 'placed_at' => now(),
                ]);

                foreach ($prepared as $row) {
                    $item = $row['item'];
                    $product = $row['product'];
                    $variant = $row['variant'];
                    $unitPrice = $row['unitPrice'];
                    $order->items()->create([
                        'product_id' => $product->id, 'product_variant_id' => $variant?->id,
                        'seller_id' => $product->seller_id,
                        'product_name' => $product->name.($variant ? ' — '.$variant->name : ''),
                        'sku' => $variant?->sku ?? $product->sku, 'unit_price' => $unitPrice,
                        'quantity' => $item->quantity, 'line_total' => $unitPrice * $item->quantity,
                    ]);
                    $variant ? $variant->decrement('stock', $item->quantity) : $product->decrement('stock', $item->quantity);
                }

                $order->payment()->create([
                    'provider' => 'cod', 'status' => 'pending', 'amount' => $order->total,
                    'currency' => 'PHP', 'idempotency_key' => $validated['idempotency_key'].'-'.$order->id,
                ]);
                $order->statusHistory()->create([
                    'changed_by' => auth()->id(), 'status' => 'placed', 'note' => 'Order placed by buyer.',
                ]);
            }
            $cart->items()->delete();
        });

        return redirect()->route('buyer.orders')->with('status', 'Order placed successfully.');
    }

    public function wishlist()
    {
        $items = auth()->user()->wishlistItems()->with('product')->latest()->get();

        return view('buyer.wishlist', compact('items'));
    }

    public function toggleWishlist(Product $product)
    {
        $item = Wishlist::where('user_id', auth()->id())->where('product_id', $product->id)->first();
        $item ? $item->delete() : Wishlist::create(['user_id' => auth()->id(), 'product_id' => $product->id]);

        return back()->with('status', $item ? 'Removed from wishlist.' : 'Saved to wishlist.');
    }

    public function account() { return view('buyer.account'); }
    public function chat() { return view('buyer.chat'); }

    private function cartSubtotal(Cart $cart): float
    {
        return $cart->items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
    }
}
