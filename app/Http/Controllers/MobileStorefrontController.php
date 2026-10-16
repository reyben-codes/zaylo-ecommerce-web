<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileStorefrontController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        $savedIds = $this->savedIds($request);
        $present = fn ($products) => $products->map(
            fn (Product $product) => MobilePayload::product($product, saved: isset($savedIds[$product->id]))
        )->values();

        // Signed-in buyers also get the web dashboard's sections, using its queries.
        $dashboard = [];
        if ($request->user('sanctum')) {
            $flash = Product::usesLegacySchema()
                ? $this->availableProducts()->whereNotNull('original_price')
                : $this->availableProducts()->whereHas('variants', fn ($query) => $query->whereNotNull('original_price_minor'));
            $dashboard = [
                'flash_sales' => $present($flash->inRandomOrder()->limit(5)->get()),
                'suggested' => $present($this->availableProducts()->inRandomOrder()->limit(10)->get()),
            ];
        }

        return response()->json($dashboard + [
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

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => ['nullable', Rule::in(['men', 'women', 'unisex'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'price_low', 'price_high'])],
        ]);

        $query = $this->availableProducts();

        if ($category = $validated['category'] ?? null) {
            if (Product::usesLegacySchema()) {
                $category === 'fashion'
                    ? $query->whereIn('category', config('marketplace.fashion_categories'))
                    : $query->where('category', $category);
            } else {
                $category === 'fashion'
                    ? $query->whereHas('category', fn ($builder) => $builder->whereIn('slug', config('marketplace.fashion_categories')))
                    : $query->whereHas('category', fn ($builder) => $builder->where('slug', $category));
            }
        }

        if ($gender = $validated['gender'] ?? null) {
            $query->where('gender', $gender);
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        match ($validated['sort'] ?? 'newest') {
            'price_low' => Product::usesLegacySchema()
                ? $query->orderBy('price')
                : $query->orderBy(ProductVariant::select('price_minor')->whereColumn('product_id', 'products.id')->where('is_active', true)->oldest('id')->limit(1)),
            'price_high' => Product::usesLegacySchema()
                ? $query->orderByDesc('price')
                : $query->orderByDesc(ProductVariant::select('price_minor')->whereColumn('product_id', 'products.id')->where('is_active', true)->oldest('id')->limit(1)),
            default => $query->latest(),
        };

        $savedIds = $this->savedIds($request);

        return response()->json([
            'products' => $query->paginate(20)->through(
                fn (Product $product) => MobilePayload::product($product, saved: isset($savedIds[$product->id]))
            ),
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->stock > 0, 404);

        $product->load([...$this->relations(), 'seller', 'variants' => fn ($query) => $query->where('is_active', true)]);
        $product->active_variants_count = $product->variants->count();

        return response()->json([
            'product' => MobilePayload::product($product, true, isset($this->savedIds($request)[$product->id])),
        ]);
    }

    private function availableProducts()
    {
        $query = Product::query()->where('is_active', true)->with($this->relations());

        if (Product::usesLegacySchema()) {
            return $query->where('stock', '>', 0)
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
        }

        return $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0))
            ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
    }

    private function relations(): array
    {
        return Product::usesLegacySchema()
            ? ['images']
            : ['images', 'defaultVariant', 'category'];
    }

    /** Public endpoints still mark saved products when the app sends a token. */
    private function savedIds(Request $request): array
    {
        $user = $request->user('sanctum');

        return $user ? array_flip($user->wishlistItems()->pluck('product_id')->all()) : [];
    }
}
