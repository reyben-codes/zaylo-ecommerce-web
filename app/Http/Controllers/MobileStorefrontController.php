<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\PlatformPolicy;
use App\Models\Product;
use App\Models\ProductReview;
use App\Support\MobilePayload;
use App\Support\ProductCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The mobile app's public storefront: home sections, the catalog, product pages, reviews and policies. */
class MobileStorefrontController extends Controller
{
    /** Policy pages that apply to buyers and riders; the seller policy stays on the website. */
    private const APP_POLICIES = ['terms', 'privacy', 'returns', 'prohibited'];

    public function home(Request $request): JsonResponse
    {
        $savedIds = $this->savedIds($request);
        $present = fn ($products) => $products->map(
            fn (Product $product) => MobilePayload::product($product, saved: isset($savedIds[$product->id]))
        )->values();

        // Signed-in buyers also get the web dashboard's sale and suggestion sections.
        $dashboard = [];
        if ($request->user('mobile')) {
            // Running flash sales first (ending soonest); without any, the current sale offers.
            $flash = $this->availableProducts()->onFlashSale()->orderBy('sale_ends_at')->limit(10)->get();
            if ($flash->isEmpty()) {
                $onSale = $this->availableProducts();
                ProductCatalog::onSale($onSale);
                $flash = $onSale->inRandomOrder()->limit(5)->get();
            }
            $dashboard = [
                'flash_sales' => $present($flash),
                'suggested' => $present($this->availableProducts()->inRandomOrder()->limit(10)->get()),
            ];
        }

        return response()->json($dashboard + [
            'announcements' => MobilePayload::announcements(Announcement::for($request->user('mobile')?->role)),
            'categories' => collect(config('marketplace.browse_categories'))->map(
                fn (array $details, string $slug) => ['id' => $slug] + $details
            )->values(),
            'hero_images' => [
                'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1600&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1445205170230-053b83016050?w=1600&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=1600&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&q=80&auto=format&fit=crop',
                'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=1600&q=80&auto=format&fit=crop',
            ],
            'new_arrivals' => $present($this->availableProducts()->latest()->limit(10)->get()),
        ]);
    }

    /** The website's catalog filters, for products in stock. */
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => ['nullable', Rule::in(array_keys(config('marketplace.genders', [])))],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_low', 'price_high'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'on_sale' => ['nullable', 'boolean'],
        ]);
        if (isset($validated['min_price'], $validated['max_price']) && $validated['max_price'] < $validated['min_price']) {
            throw ValidationException::withMessages(['max_price' => 'Maximum price must be greater than or equal to minimum price.']);
        }
        $search = ProductCatalog::normalizeSearch($validated['search'] ?? null);

        $query = $this->availableProducts();
        ProductCatalog::category($query, $validated['category'] ?? null);
        ProductCatalog::gender($query, $validated['gender'] ?? null);
        ProductCatalog::search($query, $search);
        ProductCatalog::priceRange($query, $validated['min_price'] ?? null, $validated['max_price'] ?? null);
        if ($request->boolean('on_sale')) {
            ProductCatalog::onSale($query);
        }
        ProductCatalog::sort($query, $validated['sort'] ?? ($search !== '' ? 'relevance' : 'newest'), $search);

        $savedIds = $this->savedIds($request);

        return response()->json([
            'products' => $query->paginate(20)->through(
                fn (Product $product) => MobilePayload::product($product, saved: isset($savedIds[$product->id]))
            ),
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($this->availableProducts()->whereKey($product->id)->exists(), 404);

        $product->load([...$this->relations(), 'variants' => fn ($query) => $query->where('is_active', true)])
            ->loadCount('reviews')->loadAvg('reviews', 'rating');
        $product->active_variants_count = $product->variants->count();

        return response()->json([
            'product' => MobilePayload::product($product, true, isset($this->savedIds($request)[$product->id])),
        ]);
    }

    /** Verified-purchase reviews, newest first, with the buyer's first name only. */
    public function reviews(Product $product): JsonResponse
    {
        abort_unless($product->is_listed, 404);
        $query = ProductReview::where('product_id', $product->id);
        $perRating = (clone $query)->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $count = (int) $perRating->sum();
        $page = $query->with('buyer')->latest()->paginate(10);

        return response()->json([
            'average' => $count > 0
                ? round($perRating->reduce(fn (int $sum, $total, $rating) => $sum + $rating * $total, 0) / $count, 1)
                : null,
            'count' => $count,
            'breakdown' => collect(range(5, 1))->mapWithKeys(fn (int $stars) => [$stars => (int) $perRating->get($stars, 0)]),
            'reviews' => collect($page->items())->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'buyer_name' => Str::of($review->buyer?->name ?? 'ZAYLO buyer')->before(' ')->toString(),
                'photos' => collect($review->photos ?? [])->map(fn (string $path) => MobilePayload::imageUrl(Storage::disk('public')->url($path)))->values(),
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function policies(): JsonResponse
    {
        return response()->json([
            'policies' => collect(self::APP_POLICIES)->map(fn (string $slug) => [
                'slug' => $slug,
                'title' => PlatformPolicy::PAGES[$slug],
            ])->values(),
        ]);
    }

    /**
     * A policy as the plain text the admin wrote ("## " headings, "- " bullets). Until the admin writes them,
     * the Terms of Service and Privacy Policy send their built-in text, the same the website shows.
     */
    public function policy(string $slug): JsonResponse
    {
        abort_unless(in_array($slug, self::APP_POLICIES, true), 404);
        $policy = PlatformPolicy::published($slug);
        abort_unless($policy, 404, 'This policy has not been published yet.');

        return response()->json(['policy' => [
            'slug' => $slug,
            'title' => $policy->title,
            'body' => $policy->body,
            'updated_at' => $policy->updated_at?->toIso8601String(),
            'url' => route('policies.show', $slug),
        ]]);
    }

    /** Approved, switched-on, in-stock products with what the cards need (ratings included). */
    private function availableProducts(): Builder
    {
        $query = Product::withReviewSummary()->visibleToBuyers()->with($this->relations())
            ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);

        return Product::usesLegacySchema()
            ? $query->where('stock', '>', 0)
            : $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0));
    }

    private function relations(): array
    {
        return Product::usesLegacySchema() ? ['images'] : ['images', 'defaultVariant', 'category'];
    }

    /** Public endpoints still mark saved products when the app sends a token. */
    private function savedIds(Request $request): array
    {
        $user = $request->user('mobile');

        return $user ? array_flip($user->wishlistItems()->pluck('product_id')->all()) : [];
    }
}
