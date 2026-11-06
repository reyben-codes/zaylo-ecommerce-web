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
 * Public shop pages: a cover photo, followers, rating and a way to chat, then tabs for the seller's products (with
 * search, category and sort), those products ranked by rating or sales, and the shop's categories. Buyers can follow shops and see the shops they follow.
 */
class ShopController extends Controller
{
    public function show(Request $request, int $shop)
    {
        $shop = Shop::find($shop) ?? abort(404);
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['products', 'reviews', 'categories'])],
            'q' => 'nullable|string|max:100',
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'sort' => ['nullable', Rule::in(array_keys(Shop::SORTS))],
            'rank' => ['nullable', Rule::in(array_keys(Shop::RANKINGS))],
            'section' => ['nullable', 'integer'],
        ]) + ['tab' => null, 'q' => null, 'category' => null, 'sort' => null, 'rank' => null, 'section' => null];
        $filters['q'] = trim((string) $filters['q']) ?: null;
        $filters['tab'] ??= 'products';
        $filters['sort'] ??= 'newest';
        $filters['rank'] ??= 'highest_rated';

        // The seller's own sections, shown as chips on the Products tab; a hidden, empty or unknown one shows everything.
        $sections = $shop->sections();
        $section = $filters['section'] ? $sections->firstWhere('id', (int) $filters['section']) : null;
        $filters['section'] = $section?->id;

        $products = match ($filters['tab']) {
            'products' => $shop->listing($filters['q'], $filters['category'], $filters['sort'], $section)->paginate(12),
            'reviews' => $shop->listing(null, null, $filters['rank'])->paginate(12),
            default => null,
        };

        return view('shops.show', [
            'shop' => $shop,
            'tab' => $filters['tab'],
            'products' => $products?->withQueryString()->fragment('shop-content'),
            'filters' => $filters,
            'sorts' => Shop::SORTS,
            'rankings' => Shop::RANKINGS,
            'categories' => $shop->categoryCounts(),
            'sections' => $sections,
            'section' => $section,
            'productCount' => $shop->products()->count(),
            'followers' => $shop->followerCount(),
            'following' => $shop->isFollowedBy($request->user()),
            'rating' => $shop->rating(),
            'sold' => $shop->soldCount(),
            'reviews' => $filters['tab'] === 'reviews'
                ? ProductReview::with(['buyer', 'product'])
                    ->whereIn('product_id', Product::where('seller_id', $shop->id)->select('id'))
                    ->latest()->limit(6)->get()
                : collect(),
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
