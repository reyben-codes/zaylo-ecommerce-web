<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShopFollow;
use App\Services\ChatStarter;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public shop pages: a seller's products with search, category and sort, followers, rating, recent reviews and a
 * way to chat. Buyers can follow shops and see the shops they follow.
 */
class ShopController extends Controller
{
    private const SORTS = ['newest' => 'Newest', 'popular' => 'Best selling', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low'];

    public function show(Request $request, int $shop)
    {
        $shop = Shop::find($shop) ?? abort(404);
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]) + ['q' => null, 'category' => null, 'sort' => null];
        $filters['q'] = trim((string) $filters['q']) ?: null;
        $filters['sort'] ??= 'newest';

        $query = $shop->products()->withReviewSummary()->with(['images', 'additionalCategories'])
            ->withCount(['variants as active_variants_count' => fn ($variant) => $variant->where('is_active', true)])
            ->withSum(['variants as catalog_variant_stock' => fn ($variant) => $variant->where('is_active', true)], 'stock');
        if (! Product::usesLegacySchema()) {
            $query->with(['defaultVariant', 'category']);
        }
        if ($filters['q']) {
            $pattern = '%'.addcslashes(mb_strtolower($filters['q']), '%_\\').'%';
            $query->where(fn ($match) => $match->whereRaw('LOWER(products.name) LIKE ?', [$pattern])->orWhereRaw('LOWER(products.description) LIKE ?', [$pattern]));
        }
        if ($filters['category']) {
            $query->inCategories([$filters['category']]);
        }
        match ($filters['sort']) {
            'price_low', 'price_high' => $query->orderByRaw(Product::sellingPriceSql().($filters['sort'] === 'price_high' ? ' DESC' : ' ASC'), [now(), now()]),
            'popular' => $query->withSum('orderItems as units_sold', 'quantity')->orderByDesc('units_sold')->latest('products.created_at'),
            default => $query->latest('products.created_at'),
        };
        $query->orderByDesc('products.id');

        return view('shops.show', [
            'shop' => $shop,
            'products' => $query->paginate(12)->withQueryString(),
            'filters' => $filters,
            'sorts' => self::SORTS,
            'categories' => $shop->categories(),
            'productCount' => $shop->products()->count(),
            'followers' => $shop->followerCount(),
            'following' => $shop->isFollowedBy($request->user()),
            'rating' => $shop->rating(),
            'sold' => $shop->soldCount(),
            'reviews' => ProductReview::with(['buyer', 'product'])
                ->whereIn('product_id', Product::where('seller_id', $shop->id)->select('id'))
                ->latest()->limit(6)->get(),
            'isOwner' => $request->user()?->is($shop->owner) ?? false,
        ]);
    }

    /** Follow or unfollow; answers JSON for the button, or returns to the page without JavaScript. */
    public function follow(Request $request, int $shop)
    {
        $shop = Shop::find($shop) ?? abort(404);
        $follow = ShopFollow::where('user_id', $request->user()->id)->where('shop_id', $shop->id)->first();
        $follow ? $follow->delete() : ShopFollow::firstOrCreate(['user_id' => $request->user()->id, 'shop_id' => $shop->id]);
        $following = ! $follow;

        if ($request->expectsJson()) {
            return response()->json(['following' => $following, 'followers' => $shop->followerCount()]);
        }

        return back()->with('status', $following ? "You're now following {$shop->name}." : "You unfollowed {$shop->name}.");
    }

    public function following(Request $request)
    {
        $follows = ShopFollow::where('user_id', $request->user()->id)->latest()->get();
        $shops = $follows->map(fn (ShopFollow $follow) => Shop::find($follow->shop_id))->filter()->map(fn (Shop $shop) => [
            'shop' => $shop,
            'products' => $shop->products()->count(),
            'followers' => $shop->followerCount(),
            'preview' => $shop->products()->with('images')->latest()->limit(4)->get(),
        ])->values();

        return view('buyer.following', ['shops' => $shops]);
    }

    /** Opens (or reuses) the buyer's conversation with the shop's seller. */
    public function chat(Request $request, int $shop, ChatStarter $chats)
    {
        $conversation = $chats->forShop($request->user(), $shop);

        return redirect()->route('buyer.chat', ['conversation' => $conversation->id]);
    }
}
