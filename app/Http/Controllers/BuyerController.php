<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuyerController extends Controller
{
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
        $flashProducts = Product::usesLegacySchema()
            ? (clone $base)->whereNotNull('original_price')->inRandomOrder()->limit(5)->get()
            : (clone $base)->whereHas('variants', fn ($query) => $query->whereNotNull('original_price_minor'))->inRandomOrder()->limit(5)->get();
        $suggestedProducts = (clone $base)->inRandomOrder()->limit(10)->get();

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
        $query = Product::with(['seller', 'images'])
            ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)])
            ->where('is_active', true);
        Product::usesLegacySchema()
            ? $query->where('stock', '>', 0)
            : $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0))->with(['defaultVariant', 'category']);

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
        if (! empty($validated['search'])) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$validated['search'].'%')
                ->orWhere('description', 'like', '%'.$validated['search'].'%'));
        }

        if (Product::usesLegacySchema()) {
            match ($validated['sort'] ?? 'newest') {
                'price_low' => $query->orderBy('price'),
                'price_high' => $query->orderByDesc('price'),
                default => $query->latest(),
            };
        } else {
            match ($validated['sort'] ?? 'newest') {
                'price_low' => $query->orderBy(ProductVariant::select('price_minor')->whereColumn('product_id', 'products.id')->where('is_active', true)->oldest('id')->limit(1)),
                'price_high' => $query->orderByDesc(ProductVariant::select('price_minor')->whereColumn('product_id', 'products.id')->where('is_active', true)->oldest('id')->limit(1)),
                default => $query->latest(),
            };
        }

        return view('buyer.products', [
            'products' => $query->paginate(15)->withQueryString(),
            'category' => $validated['category'] ?? null,
        ]);
    }

    public function showProduct(Product $product)
    {
        abort_unless($product->is_active && $product->stock > 0, 404);
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
            'shipped' => ['picked_up', 'in_transit'],
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

    public function cancelOrder(Order $order, CheckoutService $checkout)
    {
        $checkout->cancel($order, auth()->user());

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

        $checkout = app(CheckoutService::class);
        try {
            $voucher = $checkout->voucher($checkout->normalizeVoucherCode($request->query('voucher', '')));
        } catch (ValidationException $exception) {
            return redirect()->route('buyer.checkout.show')->withErrors($exception->errors());
        }
        $totals = $checkout->quote($items, $voucher);

        return view('buyer.checkout', [
            'items' => $items,
            'subtotal' => $totals['subtotal'],
            'shipping' => $totals['shipping'],
            'shippingDiscount' => $totals['shipping_discount'],
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
        $item = app(CartService::class)->add(
            $request->user(), $product, $validated['quantity'], $validated['product_variant_id'] ?? null,
        );

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
        app(CartService::class)->updateQuantity($cartItem, $validated['quantity']);

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
        ]);
        $checkout = app(CheckoutService::class);
        if ($checkout->alreadyPlaced($validated['idempotency_key'])) {
            return redirect()->route('buyer.orders')->with('status', 'This checkout was already completed.');
        }
        $cart = Cart::where('user_id', auth()->id())->with(['items.product', 'items.variant'])->first();
        if (! $cart || $cart->items->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        $selectedIds = $validated['cart_item_ids'] ?? $request->session()->get('checkout_cart_item_ids') ?? $cart->items->pluck('id')->all();
        $checkout->place($request->user(), $selectedIds, $validated, $checkout->normalizeVoucherCode($validated['voucher_code'] ?? null));

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

    public function chat()
    {
        return view('buyer.chat');
    }

    private function cartSubtotal(Cart $cart): float
    {
        return $cart->items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
    }
}
