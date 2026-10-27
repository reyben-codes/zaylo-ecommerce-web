<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catalog filters and sorting shared by the web storefront and the mobile API,
 * so a search or price range returns the same products on both. Prices compare
 * against the selling price, which includes any scheduled sale running now.
 */
class ProductCatalog
{
    /** Normalizes search text: trims it and collapses runs of whitespace. */
    public static function normalizeSearch(?string $search): string
    {
        return trim(preg_replace('/\s+/u', ' ', $search ?? ''));
    }

    /** Matches the main or an additional category; "fashion" covers every fashion category. */
    public static function category(Builder $query, ?string $category): void
    {
        if (! $category) {
            return;
        }
        $query->inCategories($category === 'fashion'
            ? config('marketplace.fashion_categories')
            : [$category]);
    }

    /** Unisex items are for everyone, so they also appear under Men and Women. */
    public static function gender(Builder $query, ?string $gender): void
    {
        if (! $gender) {
            return;
        }
        $gender === 'unisex'
            ? $query->where('gender', 'unisex')
            : $query->whereIn('gender', [$gender, 'unisex']);
    }

    /** Every word must match the name, description, a category or a SKU. */
    public static function search(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }
        foreach (array_unique(explode(' ', $search)) as $word) {
            $pattern = '%'.self::escapeLike($word).'%';
            $query->where(function ($match) use ($pattern) {
                $match->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$pattern]);
                if (Product::usesLegacySchema()) {
                    $match->orWhereRaw("LOWER(products.sku) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(products.category) LIKE ? ESCAPE '!'", [$pattern]);
                } else {
                    $match->orWhereHas('category', fn ($category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                }
                $match->orWhereHas('additionalCategories', fn ($extra) => $extra->whereRaw("LOWER(category) LIKE ? ESCAPE '!'", [$pattern]));
                $match->orWhereHas('variants', fn ($variant) => $variant->where('is_active', true)->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }
    }

    public static function priceRange(Builder $query, mixed $min, mixed $max): void
    {
        $priceSql = Product::sellingPriceSql();
        $priceTime = now();
        foreach (['>=' => $min, '<=' => $max] as $operator => $value) {
            if ($value !== null && $value !== '') {
                $query->whereRaw("({$priceSql}) {$operator} CAST(? AS DECIMAL(12, 2))", [$priceTime, $priceTime, $value]);
            }
        }
    }

    /** Products in a running scheduled sale, or with a manual "was" price above the price. */
    public static function onSale(Builder $query): void
    {
        $priceTime = now();
        $query->where(function ($sale) use ($priceTime) {
            $sale->whereRaw('('.Product::sellingPriceSql().') < ('.Product::regularPriceSql().')', [$priceTime, $priceTime])
                ->orWhere(function ($legacy) {
                    $legacy->whereNull('sale_type');
                    Product::usesLegacySchema()
                        ? $legacy->whereColumn('original_price', '>', 'price')
                        : $legacy->whereHas('defaultVariant', fn ($variant) => $variant->whereColumn('original_price_minor', '>', 'price_minor'));
                });
        });
    }

    /** Products buyers have received, most units first, with the count as units_sold. */
    public static function bestSelling(Builder $query): void
    {
        $delivered = fn ($items) => Product::usesLegacySchema()
            ? $items->whereHas('order', fn ($order) => $order->whereIn('status', ['delivered', 'completed']))
            : $items->whereHas('sellerOrder', fn ($order) => $order->whereIn('status', ['delivered', 'completed']));
        $query->whereHas('orderItems', $delivered)
            ->withSum(['orderItems as units_sold' => $delivered], 'quantity')
            ->orderByDesc('units_sold')->orderByDesc('products.id');
    }

    /** Sorts by relevance (with a search), newest, or selling price. */
    public static function sort(Builder $query, string $sort, string $search = ''): void
    {
        if (in_array($sort, ['price_low', 'price_high'], true)) {
            $direction = $sort === 'price_high' ? 'DESC' : 'ASC';
            $query->orderByRaw(Product::sellingPriceSql()." {$direction}", [now(), now()]);
        } elseif ($sort === 'relevance' && $search !== '') {
            $query->orderByRaw(
                "CASE WHEN LOWER(products.name) = ? THEN 0 WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                [mb_strtolower($search), self::escapeLike($search).'%']
            )->latest();
        } else {
            $query->latest();
        }
        $query->orderByDesc('products.id');
    }

    private static function escapeLike(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($text));
    }
}
