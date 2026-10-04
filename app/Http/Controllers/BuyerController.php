<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuyerController extends Controller
{
    private const SHIPPING_PER_SELLER = 120.00;

    public function dashboard()
    {
        $base = Product::where('is_active', true);
        if (Product::usesLegacySchema()) {
            $base->where('stock', '>', 0)->with('images')
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('is_active', true)]);
        } else {
            $base->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))
                ->with(['images', 'defaultVariant', 'category'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('is_active', true)]);
        }
        $flashProducts = (clone $base)->where(function ($query) {
            $query->whereRaw('('.Product::sellingPriceSql().') < ('.Product::regularPriceSql().')', [now(), now()])
                ->orWhere(function ($legacySale) {
                    $legacySale->whereNull('sale_type');
                    Product::usesLegacySchema()
                        ? $legacySale->whereColumn('original_price', '>', 'price')
                        : $legacySale->whereHas('defaultVariant', fn ($variant) => $variant->whereColumn('original_price_minor', '>', 'price_minor'));
                });
        })->inRandomOrder()->limit(5)->get();
        $suggestedProducts = (clone $base)->inRandomOrder()->limit(10)->get();

        return view('buyer.dashboard', compact('flashProducts', 'suggestedProducts') + ['user' => auth()->user()]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => 'nullable|in:men,women,unisex',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:relevance,newest,price_low,price_high',
            'min_price' => 'nullable|numeric|min:0|max:99999999.99',
            'max_price' => 'nullable|numeric|min:0|max:99999999.99',
            'availability' => 'nullable|in:all,in_stock,out_of_stock',
            'on_sale' => 'nullable|boolean',
        ]);
        if (isset($validated['min_price'], $validated['max_price']) && $validated['max_price'] < $validated['min_price']) {
            throw ValidationException::withMessages(['max_price' => 'Maximum price must be greater than or equal to minimum price.']);
        }
        $validated['search'] = trim(preg_replace('/\s+/u', ' ', $validated['search'] ?? ''));
        $validated['sort'] = $validated['sort'] ?? ($validated['search'] !== '' ? 'relevance' : 'newest');
        $validated['availability'] = $validated['availability'] ?? 'in_stock';
        $query = Product::with(['seller', 'images'])
            ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)])
            ->withSum(['variants as catalog_variant_stock' => fn ($builder) => $builder->where('is_active', true)], 'stock')
            ->where('is_active', true);
        if (! Product::usesLegacySchema()) $query->with(['defaultVariant', 'category']);
        $available = function ($builder) {
            $builder->whereHas('variants', fn ($variant) => $variant->where('is_active', true)->where('stock', '>', 0));
            if (Product::usesLegacySchema()) {
                $builder->orWhere(fn ($fallback) => $fallback->whereDoesntHave('variants', fn ($variant) => $variant->where('is_active', true))->where('stock', '>', 0));
            }
        };
        if ($validated['availability'] === 'in_stock') $query->where($available);
        if ($validated['availability'] === 'out_of_stock') $query->whereNot($available);

        if (! empty($validated['category'])) {
            if (Product::usesLegacySchema()) {
                $validated['category'] === 'fashion'
                    ? $query->whereIn('category', config('marketplace.fashion_categories'))
                    : $query->where('category', $validated['category']);
            } else {
                $validated['category'] === 'fashion'
                    ? $query->whereHas('category', fn ($category) => $category->whereIn('slug', config('marketplace.fashion_categories')))
                    : $query->whereHas('category', fn ($category) => $category->where('slug', $validated['category']));
            }
        }
        if (! empty($validated['gender'])) {
            $query->where('gender', $validated['gender']);
        }
        $escapeLike = fn ($text) => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text));
        if ($validated['search'] !== '') {
            foreach (array_unique(explode(' ', $validated['search'])) as $word) {
                $pattern = '%'.$escapeLike($word).'%';
                $query->where(function ($match) use ($pattern) {
                    $match->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$pattern]);
                    if (Product::usesLegacySchema()) {
                        $match->orWhereRaw("LOWER(products.sku) LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(products.category) LIKE ? ESCAPE '!'", [$pattern]);
                    } else {
                        $match->orWhereHas('category', fn ($category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                    }
                    $match->orWhereHas('variants', fn ($variant) => $variant->where('is_active', true)->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern]));
                });
            }
        }

        $priceSql = Product::sellingPriceSql();
        $priceTime = now();
        foreach (['min_price' => '>=', 'max_price' => '<='] as $field => $operator) {
            if (isset($validated[$field])) $query->whereRaw("({$priceSql}) {$operator} CAST(? AS DECIMAL(12, 2))", [$priceTime, $priceTime, $validated[$field]]);
        }
        if (! empty($validated['on_sale'])) {
            $query->where(function ($sale) use ($priceSql, $priceTime) {
                $sale->whereRaw('('.$priceSql.') < ('.Product::regularPriceSql().')', [$priceTime, $priceTime])
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('sale_type');
                        Product::usesLegacySchema()
                            ? $legacy->whereColumn('original_price', '>', 'price')
                            : $legacy->whereHas('defaultVariant', fn ($variant) => $variant->whereColumn('original_price_minor', '>', 'price_minor'));
                    });
            });
        }

        if (in_array($validated['sort'] ?? '', ['price_low', 'price_high'], true)) {
            $direction = $validated['sort'] === 'price_high' ? 'DESC' : 'ASC';
            $query->orderByRaw(Product::sellingPriceSql()." {$direction}", [now(), now()]);
        } elseif ($validated['sort'] === 'relevance' && $validated['search'] !== '') {
            $query->orderByRaw("CASE WHEN LOWER(products.name) = ? THEN 0 WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [mb_strtolower($validated['search']), $escapeLike($validated['search']).'%'])->latest();
        } else {
            $query->latest();
        }
        $query->orderByDesc('products.id');

        $chips = [];
        foreach (['search', 'category', 'gender', 'min_price', 'max_price', 'availability', 'on_sale'] as $key) {
            if (! isset($validated[$key]) || $validated[$key] === '' || ($key === 'availability' && $validated[$key] === 'in_stock') || ($key === 'on_sale' && ! $validated[$key])) continue;
            $label = match ($key) {
                'search' => 'Search: '.$validated[$key],
                'category' => config('marketplace.categories')[$validated[$key]],
                'gender' => ucfirst($validated[$key]),
                'min_price' => 'From ₱'.number_format($validated[$key], 2),
                'max_price' => 'Up to ₱'.number_format($validated[$key], 2),
                'availability' => $validated[$key] === 'all' ? 'All availability' : 'Sold out',
                'on_sale' => 'On sale',
            };
            $remaining = $validated;
            unset($remaining[$key]);
            $chips[] = ['label' => $label, 'url' => route('products.index', $remaining)];
        }

        return view('buyer.products', [
            'products' => $query->paginate(15)->appends($validated),
            'category' => $validated['category'] ?? null,
            'filters' => $validated, 'filterChips' => $chips,
        ]);
    }

    public function showProduct(Product $product)
    {
        abort_unless($product->is_active, 404);
        $relations = ['seller', 'variants' => fn ($query) => $query->where('is_active', true), 'images'];
        if (! Product::usesLegacySchema()) {
            $relations[] = 'category';
            $relations[] = 'defaultVariant';
        }
        $product->load($relations);

        $galleryImages = collect([
            ['path' => $product->image_url, 'alt_text' => $product->name],
        ])->concat($product->images->map(fn ($image) => [
            'path' => $image->path,
            'alt_text' => $image->alt_text ?: $product->name,
        ]))->filter(fn ($image) => filled($image['path']))
            ->unique('path')
            ->values();

        return view('buyer.product-show', compact('product', 'galleryImages'));
    }

    public function orders(Request $request)
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'to_ship', 'shipped', 'delivered', 'cancelled'])],
        ])['status'] ?? 'all';

        $statusGroups = [
            'to_ship' => ['placed', 'confirmed', 'processing', 'ready_for_pickup', 'assigned'],
            'shipped' => ['picked_up', 'in_transit', 'out_for_delivery'],
            'delivered' => ['completed'],
            'cancelled' => ['cancelled'],
        ];
        $statusCounts = auth()->user()->orders()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => $statusCounts->sum()];
        foreach ($statusGroups as $group => $statuses) {
            $counts[$group] = collect($statuses)->sum(fn ($status) => (int) $statusCounts->get($status, 0));
        }

        $orders = auth()->user()->orders()
            ->with(['items', 'payment', 'shipment', 'statusHistory' => fn ($query) => $query->oldest()])
            ->when($filter !== 'all', fn ($query) => $query->whereIn('status', $statusGroups[$filter]))
            ->orderByDesc('placed_at')
            ->paginate(10)
            ->withQueryString();

        return view('buyer.orders', compact('orders', 'filter', 'counts'));
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
        $cart->load(['items.product.seller', 'items.variant']);

        return view('buyer.cart', [
            'cart' => $cart,
            'subtotal' => $this->cartSubtotal($cart),
        ]);
    }

    public function cartSelection(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['checkout', 'remove'])],
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cart_items', 'id')->where('cart_id', $cart->id)],
        ], ['cart_item_ids.required' => 'Select at least one product first.']);

        if ($validated['action'] === 'remove') {
            $cart->items()->whereIn('id', $validated['cart_item_ids'])->delete();
            $request->session()->forget('checkout_cart_item_ids');

            return redirect()->route('buyer.cart')->with('status', 'Selected products removed from your cart.');
        }

        $request->session()->put('checkout_cart_item_ids', array_map('intval', $validated['cart_item_ids']));

        return redirect()->route('buyer.checkout.show');
    }

    public function showCheckout(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load(['items.product.seller', 'items.variant']);
        $selectedIds = $request->session()->get('checkout_cart_item_ids');
        $items = is_array($selectedIds)
            ? $cart->items->whereIn('id', $selectedIds)->values()
            : $cart->items;

        if ($items->isEmpty()) {
            return redirect()->route('buyer.cart')->withErrors(['cart' => 'Select products from your cart to continue.']);
        }

        $voucherCode = Str::upper(trim((string) $request->query('voucher', '')));
        $voucher = null;
        if ($voucherCode !== '') {
            $voucher = Voucher::where('code', $voucherCode)->first();
            if (! $voucher?->isAvailable() || $voucher->type !== 'free_shipping') {
                return redirect()->route('buyer.checkout.show')->withErrors(['voucher_code' => 'This voucher is invalid or no longer available.']);
            }
        }

        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $shipping = $items->pluck('product.seller_id')->unique()->count() * self::SHIPPING_PER_SELLER;

        return view('buyer.checkout', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'shippingDiscount' => $voucher ? $shipping : 0,
            'voucher' => $voucher,
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'estimatedShipStart' => now()->addWeekdays(2),
            'estimatedShipEnd' => now()->addWeekdays(4),
        ]);
    }

    public function addToCart(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'purchase_action' => ['nullable', Rule::in(['add_to_cart', 'buy_now'])],
        ]);
        abort_unless($product->is_active, 404);
        $legacyCart = Schema::hasColumn('cart_items', 'product_id');
        $variant = ! empty($validated['product_variant_id'])
            ? $product->variants()->whereKey($validated['product_variant_id'])->where('is_active', true)->firstOrFail()
            : ($product->variants()->where('is_active', true)->count() === 1
                ? $product->variants()->where('is_active', true)->first()
                : null);
        if (! $variant && (! $legacyCart || $product->variants()->where('is_active', true)->exists())) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Please choose an available option before adding this product to your cart.',
            ]);
        }
        $available = (int) ($variant?->stock ?? $product->stock);
        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);
        $itemQuery = $cart->items();
        if ($legacyCart) {
            $itemQuery->where('product_id', $product->id);
            $variant
                ? $itemQuery->where('product_variant_id', $variant->id)
                : $itemQuery->whereNull('product_variant_id');
        } else {
            $itemQuery->where('product_variant_id', $variant->id);
        }
        $item = $itemQuery->first();
        $newQuantity = ($item?->quantity ?? 0) + $validated['quantity'];

        if ($newQuantity > $available) {
            throw ValidationException::withMessages(['quantity' => "Only {$available} item(s) are available."]);
        }
        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $data = [
                'product_variant_id' => $variant?->id,
                'quantity' => $validated['quantity'],
            ];
            if ($legacyCart) {
                $data['product_id'] = $product->id;
            }
            $item = $cart->items()->create($data);
        }

        if (($validated['purchase_action'] ?? 'add_to_cart') === 'buy_now') {
            $request->session()->put('checkout_cart_item_ids', [$item->id]);

            return redirect()->route('buyer.checkout.show');
        }

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
            'payment_method' => ['nullable', 'string', 'in:cod,gcash'],
            'cart_item_ids' => ['nullable', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'quoted_unit_prices' => ['nullable', 'array'],
            'quoted_unit_prices.*' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $validated['payment_method'] ??= 'cod';
        if (Payment::where('idempotency_key', 'like', $validated['idempotency_key'].'%')->exists()) {
            return redirect()->route('buyer.orders')->with('status', 'This checkout was already completed.');
        }
        $cart = Cart::where('user_id', auth()->id())->with(['items.product', 'items.variant'])->first();
        if (! $cart || $cart->items->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        $selectedIds = $validated['cart_item_ids'] ?? $request->session()->get('checkout_cart_item_ids') ?? $cart->items->pluck('id')->all();
        $voucherCode = Str::upper(trim((string) ($validated['voucher_code'] ?? '')));

        DB::transaction(function () use ($selectedIds, $validated, $voucherCode) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $cart = Cart::where('user_id', auth()->id())->with(['items.product', 'items.variant'])->firstOrFail();
            $itemsToOrder = $cart->items->whereIn('id', $selectedIds);
            if ($itemsToOrder->isEmpty() || $itemsToOrder->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['cart' => 'Your cart changed. Review your selection and try again.']);
            }
            $voucher = null;
            if ($voucherCode !== '') {
                $voucher = Voucher::where('code', $voucherCode)->lockForUpdate()->first();
                if (! $voucher?->isAvailable() || $voucher->type !== 'free_shipping') {
                    throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid or no longer available.']);
                }
            }
            $address = Address::where('user_id', auth()->id())->whereKey($validated['address_id'])->firstOrFail();
            if (! $address->isStructured()) {
                throw ValidationException::withMessages(['address_id' => 'Please edit this saved address and select its Philippine locations before checkout.']);
            }

            foreach ($itemsToOrder->groupBy(fn ($item) => $item->product->seller_id ?? 0) as $sellerId => $items) {
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
                    $unitPrice = (float) ($variant ? $product->priceFor($variant) : $product->price);
                    if (isset($validated['quoted_unit_prices']) && (
                        ! isset($validated['quoted_unit_prices'][$item->id])
                        || (int) round((float) $validated['quoted_unit_prices'][$item->id] * 100) !== (int) round($unitPrice * 100)
                    )) {
                        throw ValidationException::withMessages(['cart' => 'A product price changed since you reviewed checkout. Please review the updated total before placing your order.']);
                    }
                    $prepared->push(compact('item', 'product', 'variant', 'unitPrice'));
                }

                $subtotal = $prepared->sum(fn ($row) => $row['unitPrice'] * $row['item']->quantity);
                $shippingDiscount = $voucher ? self::SHIPPING_PER_SELLER : 0;
                $shippingFee = self::SHIPPING_PER_SELLER - $shippingDiscount;
                $order = Order::create([
                    'order_number' => 'ZAY-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'user_id' => auth()->id(), 'seller_id' => $sellerId ?: null, 'status' => 'placed',
                    'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $subtotal + $shippingFee,
                    'voucher_code' => $voucher?->code, 'shipping_discount' => $shippingDiscount,
                    'payment_method' => $validated['payment_method'], 'recipient_name' => $address->recipient_name,
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
                    'provider' => $validated['payment_method'], 'status' => 'pending', 'amount' => $order->total,
                    'currency' => 'PHP', 'idempotency_key' => $validated['idempotency_key'].'-'.$order->id,
                ]);
                $order->statusHistory()->create([
                    'changed_by' => auth()->id(), 'status' => 'placed', 'note' => 'Order placed by buyer.',
                ]);
            }
            $voucher?->increment('used_count');
            $cart->items()->whereIn('id', $selectedIds)->delete();
        });

        $request->session()->forget('checkout_cart_item_ids');

        return redirect()->route('buyer.orders')->with('status', 'Order placed successfully.');
    }

    public function wishlist()
    {
        $items = auth()->user()->wishlistItems()->with('product.images')->latest()->get();

        return view('buyer.wishlist', compact('items'));
    }

    public function toggleWishlist(Product $product)
    {
        $item = Wishlist::where('user_id', auth()->id())->where('product_id', $product->id)->first();
        $item ? $item->delete() : Wishlist::create(['user_id' => auth()->id(), 'product_id' => $product->id]);

        return back()->with('status', $item ? 'Removed from wishlist.' : 'Saved to wishlist.');
    }

    public function account(Request $request)
    {
        return view('buyer.account', [
            'user' => $request->user(),
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('label')->orderBy('id')->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($user->google_id && $validated['email'] !== $user->email) {
            throw ValidationException::withMessages([
                'email' => 'The email address linked to Google cannot be changed here.',
            ]);
        }

        $emailChanged = $validated['email'] !== $user->email;
        $user->fill($validated);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if (! $emailChanged) {
            return back()->with('status', 'Profile settings updated.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('verification.notice')
                ->withErrors(['otp' => 'Your email was updated, but we could not send a verification code. Please use Resend code.']);
        }

        return redirect()->route('verification.notice')->with('status', 'Profile updated. Verify your new email address to continue.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = filled($user->getAuthPassword());
        $validated = $request->validate([
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('status', $hasPassword ? 'Password updated.' : 'Password created. You can now also sign in with email.');
    }

    private function cartSubtotal(Cart $cart): float
    {
        return $cart->items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
    }
}
