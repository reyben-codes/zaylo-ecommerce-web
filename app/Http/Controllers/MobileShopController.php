<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopFollow;
use App\Support\MobilePayload;
use App\Support\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The app's shop pages: finding shops, a shop's details and products, and the shops a buyer follows. */
class MobileShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['required', 'string', 'max:100']]);
        $viewer = $request->user('mobile');

        return response()->json([
            'shops' => Shop::search($validated['search'])->map(fn (Shop $shop) => MobilePayload::shop($shop, $viewer))->values(),
        ]);
    }

    public function show(Request $request, int $shop): JsonResponse
    {
        $shop = Shop::find($shop) ?? abort(404);

        return response()->json(['shop' => MobilePayload::shop($shop, $request->user('mobile')) + [
            'categories' => collect($shop->categoryCounts())
                ->map(fn (array $category, string $key) => ['id' => $key] + $category)->values(),
            // The seller's own sections, in the seller's order (only shown, non-empty ones).
            'sections' => $shop->sections()
                ->map(fn ($section) => ['id' => $section->id, 'name' => $section->name, 'count' => $section->product_count])->values(),
        ]]);
    }

    /** Sold-out products stay listed, as on the website's shop page. */
    public function products(Request $request, int $shop): JsonResponse
    {
        $shop = Shop::find($shop) ?? abort(404);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'sort' => ['nullable', Rule::in([...array_keys(Shop::SORTS), ...array_keys(Shop::RANKINGS)])],
            'section' => ['nullable', 'integer'],
        ]);
        // An unknown, hidden or empty section shows the whole shop, as on the website.
        $section = $shop->section(isset($validated['section']) ? (int) $validated['section'] : null);
        $user = $request->user('mobile');
        $savedIds = $user ? array_flip($user->wishlistItems()->pluck('product_id')->all()) : [];

        return response()->json([
            'products' => $shop->listing(trim((string) ($validated['search'] ?? '')) ?: null, $validated['category'] ?? null, $validated['sort'] ?? 'newest', $section)
                ->paginate(20)->through(fn (Product $product) => MobilePayload::product($product, saved: isset($savedIds[$product->id]))),
        ]);
    }

    public function following(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'shops' => ShopFollow::where('user_id', $user->id)->latest()->get()
                ->map(fn (ShopFollow $follow) => Shop::find($follow->shop_id))->filter()
                ->map(fn (Shop $shop) => MobilePayload::shop($shop, $user))->values(),
        ]);
    }
}
