<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GcashPayments;
use App\Services\ShippingRates;
use App\Services\Vouchers;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Buyer saved items, bag and checkout for the mobile app. The rules live in CartService and CheckoutService,
 * shared with the website, so the ID check, sale prices, stock and vouchers behave the same.
 */
class MobileShoppingController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
    ) {}

    public function wishlist(Request $request): JsonResponse
    {
        $items = $request->user()->wishlistItems()
            ->whereHas('product', fn ($query) => $query->visibleToBuyers())
            ->with(['product' => fn ($query) => $query->with(Product::usesLegacySchema() ? ['images'] : ['images', 'defaultVariant', 'category'])
                ->withCount(['variants as active_variants_count' => fn ($variants) => $variants->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($variants) => $variants->where('is_active', true)], 'stock')])
            ->latest()->get();

        return response()->json([
            'products' => $items->map(fn (Wishlist $item) => MobilePayload::product($item->product, saved: true))->values(),
        ]);
    }

    public function toggleWishlist(Request $request, Product $product): JsonResponse
    {
        $item = Wishlist::where('user_id', $request->user()->id)->where('product_id', $product->id)->first();
        $item ? $item->delete() : Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        return response()->json([
            'saved' => ! $item,
            'message' => $item ? 'Removed from saved items.' : 'Saved for later.',
        ]);
    }

    public function cart(Request $request): JsonResponse
    {
        return response()->json($this->cartPayload($this->loadedCart($request)));
    }

    public function addToCart(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);
        $item = $this->carts->add($request->user(), $product, (int) $validated['quantity'], isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null);

        return response()->json($this->cartPayload($this->loadedCart($request)) + [
            'added_item_id' => $item->id,
            'message' => 'Added to your cart.',
        ], 201);
    }

    public function updateCartItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->ownCartItem($request, $cartItem);
        $validated = $request->validate(['quantity' => 'required|integer|min:1|max:1000000']);
        $this->carts->updateQuantity($cartItem, (int) $validated['quantity']);

        return response()->json($this->cartPayload($this->loadedCart($request)));
    }

    public function removeCartItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->ownCartItem($request, $cartItem);
        $cartItem->delete();

        return response()->json($this->cartPayload($this->loadedCart($request)) + ['message' => 'Removed from your cart.']);
    }

    public function removeCartItems(Request $request): JsonResponse
    {
        $cart = $this->carts->cartFor($request->user());
        $validated = $request->validate([
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cart_items', 'id')->where('cart_id', $cart->id)],
        ], ['cart_item_ids.required' => 'Select at least one product first.']);
        $cart->items()->whereIn('id', $validated['cart_item_ids'])->delete();

        return response()->json($this->cartPayload($this->loadedCart($request)) + [
            'message' => 'Selected products removed from your cart.',
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'in:cod,gcash'],
        ], ['cart_item_ids.required' => 'Select at least one product first.']);

        $items = $this->selectedItems($request, $validated['cart_item_ids']);
        $voucher = $this->checkout->voucher($this->checkout->normalizeVoucherCode($validated['voucher_code'] ?? null));
        $this->checkout->ensureVoucherApplies($voucher, $items);
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get();
        // Delivery is assigned by each seller, never by the buyer or an old app's saved selection.
        $address = $addresses->first(fn ($address) => $address->isStructured());
        $partner = null;

        return response()->json([
            'items' => $items->map(fn (CartItem $item) => MobilePayload::cartItem($item))->values(),
            'totals' => $this->checkout->quote($items, $voucher, $address, $partner, $validated['payment_method'] ?? 'cod'),
            'voucher_code' => $voucher?->code,
            // What the voucher does, e.g. "10% off, up to ₱100" (the app shows it once applied).
            'voucher_label' => $voucher?->describe(),
            'available_vouchers' => $this->checkout->availableVouchersFor($items),
            // The flat fee, which older app builds charge per seller.
            'shipping_per_seller' => CheckoutService::SHIPPING_PER_SELLER,
            // Empty lists keep older clients compatible while removing buyer-side delivery selection.
            'logistics_partners' => [],
            'default_logistics_provider_id' => null,
            'logistics_selected_by' => 'seller',
            // Shipping is charged once per seller.
            'seller_count' => $items->pluck('product.seller_id')->unique()->count(),
            'couriers' => [],
            // Only 18+ products need an approved ID. identity_verified stays "may order these items" so older apps,
            // which disable Place order when it is false, only block orders that hold 18+ products.
            // GCash goes to each seller's own GCash; sellers without one cannot be paid by GCash.
            'gcash_sellers' => GcashPayments::ready()
                ? app(GcashPayments::class)->sellersFor($items)->map(fn (array $seller) => $seller + [
                    // The voucher discount this shop gives, which the buyer does not send.
                    'discount' => app(Vouchers::class)->cartDiscounts($voucher, $items)[$seller['seller_id']] ?? 0,
                ])->values()
                : [],
            'identity_verified' => ($adultItems = $this->checkout->itemsNeedingIdentity($request->user(), $items))->isEmpty(),
            'identity_required_for' => $adultItems->pluck('product.name')->unique()->values(),
            'estimated_delivery' => [
                'from' => now()->addWeekdays(2)->toDateString(),
                'to' => now()->addWeekdays(4)->toDateString(),
            ],
            'addresses' => $addresses->map(fn ($address) => MobilePayload::address($address))->values(),
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            'notes' => 'nullable|string|max:500',
            'idempotency_key' => 'required|uuid',
            'payment_method' => ['nullable', 'string', 'in:cod,gcash'],
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ] + GcashPayments::checkoutRules());

        // A retried request (for example after a dropped connection) must not order twice.
        if ($this->checkout->alreadyPlaced($validated['idempotency_key'], $request->user())) {
            return response()->json([
                'message' => 'This checkout was already completed.',
                'already_placed' => true,
                'orders' => $this->ordersForKey($request, $validated['idempotency_key']),
            ]);
        }

        $orders = $this->checkout->place(
            $request->user(),
            array_map('intval', $validated['cart_item_ids']),
            $validated,
            $this->checkout->normalizeVoucherCode($validated['voucher_code'] ?? null),
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'already_placed' => false,
            'orders' => $orders->map(fn (Order $order) => MobilePayload::order($order->load('items.product.images')))->values(),
        ], 201);
    }

    private function loadedCart(Request $request): Cart
    {
        return $this->carts->cartFor($request->user())
            ->load(['items' => fn ($query) => $query->latest('id'), 'items.product.images', 'items.variant']);
    }

    private function cartPayload(Cart $cart): array
    {
        $items = $cart->items->filter(fn (CartItem $item) => $item->product !== null)->values();

        return [
            'items' => $items->map(fn (CartItem $item) => MobilePayload::cartItem($item))->values(),
            'count' => (int) $items->sum('quantity'),
            'subtotal' => (float) $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity),
        ];
    }

    /** @return Collection<int, CartItem> */
    private function selectedItems(Request $request, array $ids): Collection
    {
        $items = $this->loadedCart($request)->items->whereIn('id', $ids)->values();
        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages(['cart_item_ids' => 'Your cart changed. Review your selection and try again.']);
        }

        return $items;
    }

    private function ordersForKey(Request $request, string $key): Collection
    {
        return $this->checkout->ordersForKey($request->user(), $key)->load('items.product.images')
            ->map(fn (Order $order) => MobilePayload::order($order))->values();
    }

    public function claimVoucher(Request $request, string $code): JsonResponse
    {
        $voucher = $this->checkout->voucher($this->checkout->normalizeVoucherCode($code));
        $claim = app(\App\Services\Vouchers::class)->claim($voucher, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Voucher {$voucher->code} claimed successfully!",
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'claimed_at' => $claim->claimed_at?->toISOString(),
            ],
        ]);
    }

    private function ownCartItem(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->cart?->user_id === $request->user()->id, 403);
    }
}
