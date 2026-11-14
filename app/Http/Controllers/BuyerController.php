<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Services\BuyAgain;
use App\Support\BuyerOrderTabs;
use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ParcelTracking;
use App\Services\ShippingRates;
use App\Services\OrderReceipts;
use App\Support\MobilePayload;
use App\Support\OrderParties;
use App\Support\ProductCatalog;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuyerController extends Controller
{
    use UpdatesAccountSettings;

    public function __construct(private readonly CartService $carts, private readonly CheckoutService $checkoutService)
    {
    }

    public function dashboard()
    {
        $base = Product::withReviewSummary()->visibleToBuyers();
        if (Product::usesLegacySchema()) {
            $base->where('stock', '>', 0)->with(['images', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($query) => $query->where('is_active', true)], 'stock');
        } else {
            $base->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))
                ->with(['images', 'defaultVariant', 'category', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($query) => $query->where('is_active', true)], 'stock');
        }
        // Flash sales first, ending soonest first; without any running, the section shows current sale offers.
        $flashProducts = (clone $base)->onFlashSale()->orderBy('sale_ends_at')->limit(10)->get();
        $flashEndsAt = $flashProducts->min('sale_ends_at');
        $flashProducts = $flashProducts->isNotEmpty() ? $flashProducts : (clone $base)->where(function ($query) {
            $query->whereRaw('('.Product::sellingPriceSql().') < ('.Product::regularPriceSql().')', [now(), now()])
                ->orWhere(function ($legacySale) {
                    $legacySale->whereNull('sale_type');
                    Product::usesLegacySchema()
                        ? $legacySale->whereColumn('original_price', '>', 'price')
                        : $legacySale->whereHas('defaultVariant', fn ($variant) => $variant->whereColumn('original_price_minor', '>', 'price_minor'));
                });
        })->inRandomOrder()->limit(5)->get();
        $suggestedProducts = (clone $base)->inRandomOrder()->limit(10)->get();
        $topProducts = (clone $base)->tap(fn ($query) => ProductCatalog::bestSelling($query))->limit(5)->get();
        $topShops = Shop::topSelling();

        return view('buyer.dashboard', compact('flashProducts', 'flashEndsAt', 'suggestedProducts', 'topProducts', 'topShops') + ['user' => auth()->user()]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => ['nullable', Rule::in(array_keys(config('marketplace.genders')))],
            'size' => 'nullable|string|max:40',
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
        // Show everything by default; sold-out products are grayed out and listed after available ones.
        $validated['availability'] = $validated['availability'] ?? 'all';
        $query = Product::withReviewSummary()->with(['seller', 'images', 'additionalCategories'])
            ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)])
            ->withSum(['variants as catalog_variant_stock' => fn ($builder) => $builder->where('is_active', true)], 'stock')
            ->visibleToBuyers();
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
            $query->inCategories([$validated['category']]);
        }
        if (! empty($validated['gender'])) {
            // Unisex items also appear under Men and Women; Kids (boys or girls) items under Boys and Girls.
            $query->whereIn('products.gender', config('marketplace.gender_matches')[$validated['gender']]);
        }
        $validated['size'] = trim((string) ($validated['size'] ?? '')) ?: null;
        if ($validated['size'] !== null) {
            $size = $validated['size'];
            $query->whereHas('variants', fn ($variant) => $variant->where('is_active', true)
                ->where(Product::usesLegacySchema() ? 'size' : 'options->size', $size));
        }
        $escapeLike = fn ($text) => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text));
        if ($validated['search'] !== '') {
            foreach (array_unique(explode(' ', $validated['search'])) as $word) {
                $pattern = '%'.$escapeLike($word).'%';
                $forGroup = self::genderForWord($word);
                $named = ProductCatalog::categoriesNamed($word);
                $query->where(function ($match) use ($pattern, $forGroup, $named) {
                    if ($forGroup) {
                        $match->whereIn('products.gender', config('marketplace.gender_matches')[$forGroup]);
                    }
                    $match->orWhereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$pattern]);
                    if ($named !== []) {
                        $match->orWhere(fn ($category) => $category->inCategories($named));
                    }
                    if (Product::usesLegacySchema()) {
                        $match->orWhereRaw("LOWER(products.sku) LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(products.category) LIKE ? ESCAPE '!'", [$pattern]);
                    } else {
                        $match->orWhereHas('category', fn ($category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                    }
                    $match->orWhereHas('additionalCategories', fn ($extra) => $extra->whereRaw("LOWER(category) LIKE ? ESCAPE '!'", [$pattern]));
                    $match->orWhereRaw("LOWER(products.custom_tags) LIKE ? ESCAPE '!'", [$pattern]);
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

        if ($validated['availability'] === 'all') {
            ProductCatalog::soldOutLast($query);
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

        // Search-as-you-type suggestions (public/js/live-search.js): the best few matches, ranked like the catalog.
        if ($request->boolean('suggest')) {
            return response()->json([
                'products' => $query->limit(6)->get()->map(fn (Product $product) => [
                    'name' => $product->name,
                    'url' => route('products.show', $product),
                    'image' => $product->image_url ?: $product->images->first()?->path ?: asset('images/ZAYLO_ICON_DARK.png'),
                    'price' => '₱'.number_format($product->price, 2),
                    'sold_out' => (bool) $product->is_sold_out,
                ]),
                'shops' => Shop::search($validated['search'], 3)->map(fn (Shop $shop) => [
                    'name' => $shop->name,
                    'url' => $shop->url(),
                    'image' => $shop->owner->avatar_url ?: asset('images/ZAYLO_ICON_DARK.png'),
                ]),
            ]);
        }

        $chips = [];
        foreach (['search', 'category', 'gender', 'size', 'min_price', 'max_price', 'availability', 'on_sale'] as $key) {
            if (! isset($validated[$key]) || $validated[$key] === '' || ($key === 'availability' && $validated[$key] === 'all') || ($key === 'on_sale' && ! $validated[$key])) continue;
            $label = match ($key) {
                'search' => 'Search: '.$validated[$key],
                'category' => config('marketplace.categories')[$validated[$key]],
                'gender' => $validated[$key] === 'kids' ? 'All kids' : config('marketplace.genders')[$validated[$key]],
                'size' => 'Size: '.$validated[$key],
                'min_price' => 'From ₱'.number_format($validated[$key], 2),
                'max_price' => 'Up to ₱'.number_format($validated[$key], 2),
                'availability' => $validated[$key] === 'in_stock' ? 'In stock only' : 'Sold out',
                'on_sale' => 'On sale',
            };
            $remaining = $validated;
            unset($remaining[$key]);
            $chips[] = ['label' => $label, 'url' => route('products.index', $remaining)];
        }

        return view('buyer.products', [
            'products' => $query->paginate(15)->appends($validated),
            'shops' => $validated['search'] !== '' && $request->integer('page', 1) === 1 ? Shop::search($validated['search']) : collect(),
            'category' => $validated['category'] ?? null,
            'filters' => $validated, 'filterChips' => $chips,
            'sizes' => $this->catalogSizes(),
        ]);
    }

    /** The "For" group a search word means, e.g. "kids" or "toddler" → kids, "girls" → girls. */
    private static function genderForWord(string $word): ?string
    {
        return match (mb_strtolower($word)) {
            'kid', 'kids', 'child', 'children', 'toddler', 'toddlers', 'baby', 'babies' => 'kids',
            'girl', 'girls' => 'girls',
            'boy', 'boys' => 'boys',
            default => null,
        };
    }

    /**
     * Sizes buyers can filter by: every size of an option on sale, kids' sizes first, then adult sizes in their
     * usual order, then anything else alphabetically.
     */
    private function catalogSizes(): array
    {
        $variants = \Illuminate\Support\Facades\DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_active', true)->where('product_variants.is_active', true);
        $sizes = (Product::usesLegacySchema()
            ? $variants->whereNotNull('product_variants.size')->distinct()->pluck('product_variants.size')
            : $variants->whereNotNull('product_variants.options')->pluck('product_variants.options')
                ->map(fn ($options) => data_get(json_decode((string) $options, true), 'size')))
            ->map(fn ($size) => trim((string) $size))->filter()->unique(fn ($size) => mb_strtolower($size));

        $order = collect(config('marketplace.kids_size_presets'))->flatten()
            ->concat(collect(config('marketplace.size_presets'))->flatten())
            ->unique()->values()->flip();

        return $sizes->sortBy(fn ($size) => [$order->get($size, PHP_INT_MAX), mb_strtolower($size)])->values()->take(80)->all();
    }

    public function showProduct(Product $product)
    {
        // Unapproved or hidden listings exist only for their seller and admins, who see a preview notice.
        $preview = ! $product->is_listed;
        abort_if($preview && ! auth()->user()?->can('update', $product), 404);
        $relations = ['seller', 'variants' => fn ($query) => $query->where('is_active', true), 'images'];
        if (! Product::usesLegacySchema()) {
            $relations[] = 'category';
            $relations[] = 'defaultVariant';
        }
        $product->load($relations);

        // Name the picker after what actually varies, and link each color to its photos ("<product> — <color>" alt text).
        $variants = $product->variants;
        $colors = $variants->map->color_label->filter()->unique();
        $sized = $variants->filter(fn ($variant) => $variant->size_label !== null);
        $optionLabel = $sized->isNotEmpty()
            ? ($sized->count() === $variants->count() ? 'Size' : 'Option')
            : ($variants->count() > 1 && $colors->count() === $variants->count() ? 'Color' : 'Option');
        $photoKey = fn ($variant) => $variant->color_label ?? $variant->name;
        $photoFor = fn (string $key) => $product->images->first(fn ($image) => Str::endsWith((string) $image->alt_text, ' — '.$key))?->path;
        $variantImages = $variants->mapWithKeys(fn ($variant) => [$variant->id => $photoFor($photoKey($variant))]);

        // Products sold in several colors that each come in sizes get a color step, then that color's own sizes.
        $colorGroups = $sized->isNotEmpty() && $colors->count() > 1
            ? $variants->groupBy(fn ($variant) => $variant->color_label ?? '')->map(fn ($group, $color) => [
                'name' => $color !== '' ? $color : 'Other',
                'photo' => $photoFor((string) $color),
                'stock' => (int) $group->sum('stock'),
                'variants' => $group->values(),
            ])->values()
            : null;

        // Photos of a sold-out color (every size gone) are grayed out in the gallery.
        $soldOutKeys = $variants->groupBy($photoKey)->filter(fn ($group) => $group->sum('stock') < 1)->keys();
        $soldOutPaths = $product->images->filter(fn ($image) => $soldOutKeys->contains(
            fn ($key) => Str::endsWith((string) $image->alt_text, ' — '.$key)
        ))->pluck('path');

        $galleryImages = collect([
            ['path' => $product->image_url, 'alt_text' => $product->name],
        ])->concat($product->images->map(fn ($image) => [
            'path' => $image->path,
            'alt_text' => $image->alt_text ?: $product->name,
        ]))->filter(fn ($image) => filled($image['path']))
            ->unique('path')
            ->map(fn ($image) => $image + ['sold_out' => $soldOutPaths->contains($image['path'])])
            ->values();

        $reviewQuery = \App\Models\ProductReview::where('product_id', $product->id);
        $reviewCount = (clone $reviewQuery)->count();
        $reviewAverage = (clone $reviewQuery)->avg('rating');
        $reviews = $reviewQuery->with('buyer')->latest()->paginate(5, ['*'], 'reviews_page');
        $shop = $product->seller_id ? \App\Support\Shop::find((int) $product->seller_id) : null;
        $reviewForm = $this->reviewFormFor($product, $preview);

        return view('buyer.product-show', compact('product', 'galleryImages', 'reviews', 'reviewCount', 'reviewAverage', 'optionLabel', 'variantImages', 'colorGroups', 'shop', 'preview', 'reviewForm'));
    }

    /**
     * What the reviews section offers: "write" for a buyer who has not reviewed the product from its page yet,
     * "written" once they have, "login" for guests, or null (other accounts, previews, or before the migration).
     */
    private function reviewFormFor(Product $product, bool $preview): ?string
    {
        if ($preview) {
            return null;
        }
        $user = auth()->user();
        if (! $user) {
            return 'login';
        }
        if ($user->role !== 'buyer' || ! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return null;
        }

        return app(\App\Services\ProductReviews::class)->openReviewBy($user, $product) ? 'written' : 'write';
    }

    public function orders(Request $request, OrderReceipts $receipts)
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(BuyerOrderTabs::tabs())],
        ])['status'] ?? 'all';
        $receipts->autoConfirmDue();
        app(\App\Services\GcashPayments::class)->expireDue($this->checkoutService, buyerId: auth()->id());

        // Cancelled orders move to Purchase history after a couple of days (BuyerOrderTabs).
        $counts = BuyerOrderTabs::counts(auth()->user()->orders()->getQuery());

        $orders = BuyerOrderTabs::apply(auth()->user()->orders()->getQuery(), $filter)
            ->with(['items.review', 'items.product.images', 'payment', 'shipment', 'statusHistory' => fn ($query) => $query->oldest()])
            ->when(Schema::hasTable('dispute_messages'), fn ($query) => $query->with('disputes'))
            ->orderByDesc('placed_at')
            ->paginate(10)
            ->withQueryString();

        return view('buyer.orders', compact('orders', 'filter', 'counts'));
    }

    /** The parcel on a map: the buyer's pin, and the rider moving towards it while it is out for delivery. */
    public function trackOrder(Order $order, ParcelTracking $tracking)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) auth()->id(), 404);

        return view('buyer.track-order', ['order' => $order, 'tracking' => $tracking->forBuyer($order)]);
    }

    public function orderTracking(Order $order, ParcelTracking $tracking)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) auth()->id(), 404);

        return response()->json(['tracking' => $tracking->forBuyer($order)]);
    }

    public function cancelOrder(Request $request, Order $order)
    {
        $data = $request->validate(self::cancelRules(), self::cancelMessages());
        $this->checkoutService->cancel($order, $request->user(), $data['reason'], filled($data['note'] ?? null) ? trim($data['note']) : null);

        return back()->with('status', 'Order cancelled and stock restored. You can buy it again anytime from Cancelled.');
    }

    /** A cancel needs one of the listed reasons; "Other" needs a few words. Also used by the app. */
    public static function cancelRules(): array
    {
        return [
            'reason' => ['required', Rule::in(array_keys(BuyerOrderTabs::CANCEL_REASONS))],
            'note' => ['nullable', 'required_if:reason,other', 'string', 'min:3', 'max:255'],
        ];
    }

    public static function cancelMessages(): array
    {
        return [
            'reason.required' => 'Choose why you are cancelling.',
            'reason.in' => 'Choose one of the listed reasons.',
            'note.required_if' => 'Tell us the reason in a few words.',
        ];
    }

    /** Puts the products of an earlier order back in the cart. */
    public function buyAgain(Order $order, BuyAgain $buyAgain)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) auth()->id(), 404);
        $result = $buyAgain->add(auth()->user(), $order);

        return $result['added']
            ? redirect()->route('buyer.cart')->with('status', BuyAgain::message($result))
            : back()->with('error', BuyAgain::message($result));
    }

    /** The buyer received a delivered order, which completes it. */
    public function confirmReceived(Order $order, OrderReceipts $receipts)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) auth()->id(), 404);
        $receipts->confirm($order, auth()->user());

        return back()->with('status', 'Thank you! Order '.$order->order_number.' is complete. You can now rate your items.');
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
            try {
                $voucher = $this->checkoutService->voucher($voucherCode);
                $this->checkoutService->ensureVoucherApplies($voucher, $items);
            } catch (\Illuminate\Validation\ValidationException $error) {
                return redirect()->route('buyer.checkout.show')->withErrors($error->errors());
            }
        }
        // Discount vouchers come off the items of shops that joined them (shop id => pesos).
        $voucherDiscounts = app(\App\Services\Vouchers::class)->cartDiscounts($voucher, $items);

        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $sellerCount = $items->pluck('product.seller_id')->unique()->count();
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get();
        // Delivery partners are selected by sellers during fulfilment.

        return view('buyer.checkout', [
            'identityVerified' => $request->user()->isIdentityVerified(),
            // An approved ID is only needed for products the seller marked 18+.
            'identityItems' => $this->checkoutService->itemsNeedingIdentity($request->user(), $items),
            'identityStatus' => Schema::hasTable('identity_verifications') ? $request->user()->latestIdentityVerification()->value('status') : null,
            'items' => $items,
            'subtotal' => $subtotal,
            'sellerCount' => $sellerCount,
            'standardFee' => ShippingRates::STANDARD_FEE,
            'voucher' => $voucher,
            'availableVouchers' => $this->checkoutService->availableVouchersFor($items),
            'addresses' => $addresses,
            'estimatedShipStart' => now()->addWeekdays(2),
            'estimatedShipEnd' => now()->addWeekdays(4),
            // GCash goes to each seller's own GCash, so checkout lists them with a reference field each.
            'gcashSellers' => \App\Services\GcashPayments::ready() ? app(\App\Services\GcashPayments::class)->sellersFor($items) : collect(),
            'voucherDiscounts' => $voucherDiscounts,
        ]);
    }

    public function claimVoucher(Request $request, Voucher $voucher)
    {
        try {
            app(\App\Services\Vouchers::class)->claim($voucher, $request->user());
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'claimed',
                'voucher' => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                ],
            ]);
        }

        return back()->with('status', "Voucher {$voucher->code} claimed! You can now apply it to your order.");
    }

    public function addToCart(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'purchase_action' => ['nullable', Rule::in(['add_to_cart', 'buy_now'])],
        ]);
        $item = $this->carts->add($request->user(), $product, (int) $validated['quantity'], ! empty($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null);
        $cart = $this->carts->cartFor($request->user());

        if (($validated['purchase_action'] ?? 'add_to_cart') === 'buy_now') {
            $request->session()->put('checkout_cart_item_ids', [$item->id]);

            return redirect()->route('buyer.checkout.show');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Product added to your cart.', 'cart_url' => route('buyer.cart'), 'cart_quantity' => (int) $cart->items()->sum('quantity')]);
        }

        return back()->with('status', 'Product added to your cart.');
    }

    public function updateCart(Request $request, CartItem $cartItem)
    {
        abort_unless($cartItem->cart->user_id === auth()->id(), 403);
        $validated = $request->validate(['quantity' => 'required|integer|min:1|max:1000000']);
        $this->carts->updateQuantity($cartItem, (int) $validated['quantity']);

        return back()->with('status', 'Cart updated.');
    }

    /**
     * Saves every changed quantity at once. All-or-nothing: if any item exceeds its stock, nothing is saved.
     */
    public function updateCartItems(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $validated = $request->validate([
            'quantities' => 'required|array|min:1|max:200',
            'quantities.*' => 'required|integer|min:1|max:1000000',
        ], [
            'quantities.required' => 'Change a quantity first, then choose Update all.',
            'quantities.*.min' => 'Quantities must be at least 1. Use Remove to take an item out of your cart.',
            'quantities.*.max' => 'That quantity is more than any seller can stock.',
        ]);

        $items = $cart->items()->with(['product', 'variant'])->whereIn('id', array_keys($validated['quantities']))->get()->keyBy('id');
        if ($items->count() !== count($validated['quantities'])) {
            throw ValidationException::withMessages(['quantities' => 'Your cart changed. Refresh the page and try again.']);
        }

        $problems = [];
        foreach ($items as $id => $item) {
            $available = $item->availableStock();
            if (! $item->product->is_listed) {
                $problems["quantities.{$id}"] = "{$item->product->name} is no longer available.";
            } elseif ($validated['quantities'][$id] > $available) {
                $problems["quantities.{$id}"] = "{$item->product->name}: only {$available} left in stock.";
            }
        }
        if ($problems) {
            throw ValidationException::withMessages($problems);
        }

        DB::transaction(fn () => $items->each(fn (CartItem $item, $id) => $item->update(['quantity' => (int) $validated['quantities'][$id]])));
        $count = $items->count();

        return back()->with('status', $count === 1 ? 'Cart updated.' : "{$count} items updated.");
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
        ] + \App\Services\GcashPayments::checkoutRules());
        $validated['payment_method'] ??= 'cod';
        return DB::transaction(function () use ($request, $validated) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($this->checkoutService->alreadyPlaced($validated['idempotency_key'], $request->user())) {
                return redirect()->route('buyer.orders')->with('status', 'This checkout was already completed.');
            }
            $cart = Cart::where('user_id', auth()->id())->with(['items.product', 'items.variant'])->first();
            if (! $cart || $cart->items->isEmpty()) {
                return back()->withErrors(['cart' => 'Your cart is empty.']);
            }

            $selectedIds = $validated['cart_item_ids'] ?? $request->session()->get('checkout_cart_item_ids') ?? $cart->items->pluck('id')->all();
            $voucherCode = Str::upper(trim((string) ($validated['voucher_code'] ?? '')));

            $this->checkoutService->place($request->user(), array_map('intval', $selectedIds), $validated, $voucherCode);

            $request->session()->forget('checkout_cart_item_ids');

            return redirect()->route('buyer.orders')->with('status', 'Order placed successfully.');
        });
    }

    public function wishlist()
    {
        $items = auth()->user()->wishlistItems()->whereHas('product', fn ($product) => $product->visibleToBuyers())
            ->with(['product' => fn ($product) => $product->withReviewSummary()
                ->with(['images', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($variants) => $variants->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($variants) => $variants->where('is_active', true)], 'stock')])
            ->latest()->get();

        return view('buyer.wishlist', compact('items'));
    }

    public function toggleWishlist(Request $request, Product $product)
    {
        $item = Wishlist::where('user_id', auth()->id())->where('product_id', $product->id)->first();
        $item ? $item->delete() : Wishlist::create(['user_id' => auth()->id(), 'product_id' => $product->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => ! $item,
                'count' => Wishlist::where('user_id', auth()->id())->count(),
            ]);
        }

        return back()->with('status', $item ? 'Removed from wishlist.' : 'Saved to wishlist.');
    }

    public function account(Request $request)
    {
        $verificationEnabled = Schema::hasTable('identity_verifications');

        return view('buyer.account', [
            'user' => $request->user(),
            'identityEnabled' => $verificationEnabled,
            'identityVerified' => $request->user()->isIdentityVerified(),
            'identitySubmission' => $verificationEnabled ? $request->user()->latestIdentityVerification()->first() : null,
            'idTypes' => \App\Models\IdentityVerification::ID_TYPES,
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('label')->orderBy('id')->get(),
            'activity' => Schema::hasTable('activity_logs')
                ? \App\Models\ActivityLog::where('user_id', $request->user()->id)->latest('created_at')->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    private function cartSubtotal(Cart $cart): float
    {
        return $cart->items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
    }
}
