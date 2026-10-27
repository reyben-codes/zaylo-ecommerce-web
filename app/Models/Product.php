<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    // Admin review of seller listings; only approved products reach buyers (see scopeVisibleToBuyers).
    public const REVIEW_PENDING = 'pending';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    private array $variantInput = [];
    private ?string $coverImageInput = null;

    protected $fillable = [
        'seller_id',
        'name',
        'category',
        'gender',
        'description',
        'price',
        'original_price',
        'badge',
        'image_url',
        'stock',
        'is_active',
        'slug',
        'sku',
        'low_stock_threshold',
        'weight_grams',
        'category_id',
        'sale_type', 'sale_value', 'sale_starts_at', 'sale_ends_at', 'sale_variant_id',
        'sale_is_flash', 'flash_quantity', 'flash_sold',
        'archived_at',
    ];

    protected $casts = ['is_active' => 'boolean', 'sale_value' => 'decimal:2', 'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'sale_variant_id' => 'integer', 'sale_is_flash' => 'boolean', 'flash_quantity' => 'integer', 'flash_sold' => 'integer', 'archived_at' => 'datetime', 'reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->name).'-'.Str::lower(Str::random(6));
            if (static::usesLegacySchema()) {
                $product->sku ??= 'ZAY-'.Str::upper(Str::random(8));
                $product->attributes['low_stock_threshold'] = static::lowStockThreshold();
            }
        });

        static::saved(function (Product $product): void {
            if (static::usesLegacySchema()) {
                return;
            }

            if ($product->variantInput !== []) {
                $variant = $product->variants()->oldest('id')->first() ?? new ProductVariant(['product_id' => $product->id]);
                // Only submitted fields change on an existing variant; defaults apply to a brand-new one.
                $columns = [
                    'sku' => 'sku', 'variant_name' => 'name', 'options' => 'options', 'price_minor' => 'price_minor',
                    'original_price_minor' => 'original_price_minor', 'stock' => 'stock',
                    'low_stock_threshold' => 'low_stock_threshold', 'weight_grams' => 'weight_grams',
                ];
                $attributes = ['is_active' => $product->is_active ?? true];
                foreach ($columns as $input => $column) {
                    if (array_key_exists($input, $product->variantInput)) {
                        $attributes[$column] = $product->variantInput[$input];
                    }
                }
                if (! $variant->exists) {
                    $attributes += [
                        'sku' => 'ZAY-'.Str::upper(Str::random(8)), 'name' => 'Default',
                        'price_minor' => 0, 'stock' => 0, 'low_stock_threshold' => static::lowStockThreshold(),
                    ];
                }
                $variant->fill($attributes)->save();
                $product->variantInput = [];
                $product->unsetRelation('defaultVariant');
            }

            if ($product->coverImageInput) {
                $cover = $product->images()->where('position', 0)->first() ?? new ProductImage(['product_id' => $product->id, 'position' => 0]);
                $cover->fill(['path' => $product->coverImageInput, 'alt_text' => $product->name])->save();
            }
        });
    }

    public function setCategoryAttribute(string $value): void
    {
        if (static::usesLegacySchema()) {
            $this->attributes['category'] = $value;
            return;
        }

        $this->attributes['category_id'] = Category::firstOrCreate(
            ['slug' => $value],
            ['name' => config('marketplace.categories')[$value] ?? Str::headline($value), 'position' => 0, 'is_active' => true],
        )->id;
    }

    public function setSellerIdAttribute(mixed $value): void
    {
        if (static::usesLegacySchema()) {
            $this->attributes['seller_id'] = $value;
            return;
        }

        $user = User::find($value);
        if ($user?->hasRole('seller')) {
            $this->attributes['seller_id'] = $user->sellerShop()->id;
            return;
        }

        $this->attributes['seller_id'] = $value;
    }

    public function getCategoryKeyAttribute(): ?string
    {
        return static::usesLegacySchema()
            ? ($this->attributes['category'] ?? null)
            : $this->category?->slug;
    }

    public function getCategoryLabelAttribute(): string
    {
        if (! static::usesLegacySchema()) {
            return $this->category?->name ?? 'Uncategorized';
        }

        $key = $this->attributes['category'] ?? '';

        return config('marketplace.categories')[$key] ?? Str::headline($key ?: 'uncategorized');
    }

    public function setPriceAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['price'] = $value : $this->variantInput['price_minor'] = (int) round((float) $value * 100); }
    public function getPriceAttribute(mixed $value): string { return $this->priceFor(static::usesLegacySchema() ? null : $this->defaultVariant); }
    public function getRegularPriceAttribute(): string { return number_format(static::usesLegacySchema() ? (float) ($this->attributes['price'] ?? 0) : (($this->defaultVariant?->price_minor ?? 0) / 100), 2, '.', ''); }
    public function setOriginalPriceAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['original_price'] = $value : $this->variantInput['original_price_minor'] = filled($value) ? (int) round((float) $value * 100) : null; }
    public function getOriginalPriceAttribute(mixed $value): ?string
    {
        if ($this->sale_type) {
            return (float) $this->price < (float) $this->regular_price ? $this->regular_price : null;
        }
        return static::usesLegacySchema() ? (filled($value) ? number_format((float) $value, 2, '.', '') : null) : ($this->defaultVariant?->original_price_minor === null ? null : number_format($this->defaultVariant->original_price_minor / 100, 2, '.', ''));
    }

    public function priceFor(?ProductVariant $variant = null): string
    {
        $regular = (int) round((float) ($variant?->regular_price ?? $this->regular_price) * 100);
        $price = $regular;
        if ($this->saleIsActive() && (! $this->sale_variant_id || $this->sale_variant_id === $variant?->id)) {
            $price = $this->sale_type === 'percent'
                ? (int) round($regular * (10000 - (int) round((float) $this->sale_value * 100)) / 10000)
                : (int) round((float) $this->sale_value * 100);
        }
        return number_format(max(0, min($regular, $price)) / 100, 2, '.', '');
    }

    public function saleIsActive(): bool
    {
        $now = now();
        return in_array($this->sale_type, ['price', 'percent'], true)
            && $this->sale_starts_at && $this->sale_ends_at
            && $now->gte($this->sale_starts_at) && $now->lt($this->sale_ends_at)
            && ! ($this->sale_is_flash && $this->flashRemaining() <= 0);
    }

    /** Flash sales: a sale of at most a day on a limited number of units (after the 2026_10_27_000002 migration). */
    public static function tracksFlashSales(): bool
    {
        return Schema::hasColumn('products', 'sale_is_flash');
    }

    public function isFlashSale(): bool
    {
        return (bool) $this->sale_is_flash && $this->saleIsActive();
    }

    /** Products whose flash sale is running now with units left. */
    public function scopeOnFlashSale($query)
    {
        if (! static::tracksFlashSales()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('sale_is_flash', true)->whereNotNull('sale_type')
            ->where('sale_starts_at', '<=', now())->where('sale_ends_at', '>', now())
            ->whereColumn('flash_sold', '<', 'flash_quantity');
    }

    /** Units still available at the flash price. */
    public function flashRemaining(): int
    {
        return max(0, (int) $this->flash_quantity - (int) $this->flash_sold);
    }

    /** Whether buying [$variant] now uses the flash price (and counts against the flash quantity). */
    public function flashAppliesTo(?ProductVariant $variant = null): bool
    {
        return $this->isFlashSale() && (! $this->sale_variant_id || $this->sale_variant_id === $variant?->id);
    }

    public function getSaleStatusAttribute(): string
    {
        if (! $this->sale_type) return 'No scheduled sale';
        if (now()->lt($this->sale_starts_at)) return $this->sale_is_flash ? 'Flash sale scheduled' : 'Scheduled';
        if ($this->sale_is_flash && $this->flashRemaining() <= 0 && now()->lt($this->sale_ends_at)) return 'Flash sale sold out';
        if ($this->saleIsActive()) return $this->sale_is_flash ? 'Flash sale · '.$this->flashRemaining().' left' : 'On sale';
        return 'Ended';
    }

    public function getDiscountPercentageAttribute(): int
    {
        return (float) $this->original_price > (float) $this->price
            ? (int) round((1 - (float) $this->price / (float) $this->original_price) * 100) : 0;
    }

    public function getBadgeAttribute(?string $value): ?string
    {
        if ($this->discount_percentage > 0) return 'Sale';
        return $value === 'Sale' ? null : $value;
    }

    public static function regularPriceSql(): string
    {
        return static::usesLegacySchema() ? 'products.price' : 'COALESCE((SELECT price_minor / 100.0 FROM product_variants WHERE product_id = products.id ORDER BY id LIMIT 1), 0)';
    }

    public static function sellingPriceSql(): string
    {
        $regular = static::regularPriceSql();
        $variant = static::usesLegacySchema() ? 'NULL' : '(SELECT id FROM product_variants WHERE product_id = products.id ORDER BY id LIMIT 1)';
        // A flash sale stops once its units are sold.
        $flash = static::tracksFlashSales() ? ' AND (sale_is_flash IS NULL OR sale_is_flash = 0 OR flash_sold < flash_quantity)' : '';
        return "CASE WHEN sale_type IS NOT NULL AND sale_starts_at <= ? AND sale_ends_at > ?{$flash} AND (sale_variant_id IS NULL OR sale_variant_id = {$variant}) THEN CASE WHEN sale_type = 'percent' THEN ROUND({$regular} * (100 - sale_value) / 100, 2) WHEN sale_value < {$regular} THEN sale_value ELSE {$regular} END ELSE {$regular} END";
    }
    public function setStockAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['stock'] = (int) $value : $this->variantInput['stock'] = (int) $value; }
    public function getStockAttribute(mixed $value): int { return static::usesLegacySchema() ? (int) $value : (int) ($this->defaultVariant?->stock ?? 0); }
    public function setSkuAttribute(?string $value): void { static::usesLegacySchema() ? $this->attributes['sku'] = ($value ?: 'ZAY-'.Str::upper(Str::random(8))) : $this->variantInput['sku'] = ($value ?: 'ZAY-'.Str::upper(Str::random(8))); }
    public function getSkuAttribute(mixed $value): ?string { return static::usesLegacySchema() ? $value : $this->defaultVariant?->sku; }
    // The low stock alert is fixed platform-wide, so any submitted value is replaced with it.
    public static function lowStockThreshold(): int { return (int) config('marketplace.low_stock_threshold', 10); }
    public function setLowStockThresholdAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['low_stock_threshold'] = static::lowStockThreshold() : $this->variantInput['low_stock_threshold'] = static::lowStockThreshold(); }
    public function getLowStockThresholdAttribute(mixed $value): int { return static::lowStockThreshold(); }
    public function setWeightGramsAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['weight_grams'] = $value : $this->variantInput['weight_grams'] = filled($value) ? (int) $value : null; }
    public function getWeightGramsAttribute(mixed $value): ?int { return static::usesLegacySchema() ? (filled($value) ? (int) $value : null) : $this->defaultVariant?->weight_grams; }
    public function setImageUrlAttribute(?string $value): void { static::usesLegacySchema() ? $this->attributes['image_url'] = $value : $this->coverImageInput = $value; }
    public function getImageUrlAttribute(mixed $value): ?string { return static::usesLegacySchema() ? $value : $this->images->first()?->path; }

    public function seller(): BelongsTo
    {
        return static::usesLegacySchema()
            ? $this->belongsTo(User::class, 'seller_id')
            : $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Extra categories this product is also listed under, besides its main category. */
    public function additionalCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /** @return list<string> The main category followed by the additional ones. */
    public function getCategoryKeysAttribute(): array
    {
        return collect([$this->category_key])->merge($this->additionalCategories->pluck('category'))
            ->filter()->unique()->values()->all();
    }

    /** @return array<string, string> Category key => label, main category first. */
    public function getCategoryTagsAttribute(): array
    {
        return collect($this->category_keys)
            ->mapWithKeys(fn ($key) => [$key => config('marketplace.categories')[$key] ?? Str::headline($key)])->all();
    }

    public function getCategoryLabelsAttribute(): array
    {
        return array_values($this->category_tags);
    }

    /** Keeps the additional categories in step with the form; the main category is never repeated here. */
    public function syncAdditionalCategories(array $categories): void
    {
        $categories = array_values(array_diff(array_unique($categories), [$this->category_key]));
        $this->additionalCategories()->whereNotIn('category', $categories)->delete();
        foreach ($categories as $category) {
            $this->additionalCategories()->firstOrCreate(['category' => $category]);
        }
        $this->unsetRelation('additionalCategories');
    }

    /** Products whose main or additional category is one of the given keys. */
    public function scopeInCategories($query, array $categories)
    {
        return $query->where(function ($match) use ($categories) {
            static::usesLegacySchema()
                ? $match->whereIn('products.category', $categories)
                : $match->whereHas('category', fn ($category) => $category->whereIn('slug', $categories));
            $match->orWhereHas('additionalCategories', fn ($extra) => $extra->whereIn('category', $categories));
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function scopeWithReviewSummary($query)
    {
        return $query->withCount('reviews')->withAvg('reviews', 'rating');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->oldestOfMany();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy(static::usesLegacySchema() ? 'sort_order' : 'position');
    }

    /** "For women", "For kids", "Unisex"…, or null when the seller did not say. */
    public function getGenderLabelAttribute(): ?string
    {
        return match ($this->gender) {
            null, '' => null,
            'unisex' => 'Unisex',
            'kids' => 'For kids',
            default => 'For '.$this->gender,
        };
    }

    public function isForKids(): bool
    {
        return in_array($this->gender, config('marketplace.kids_genders'), true);
    }

    /** Standard sizes for this product's main category, in kids' sizes when it is for girls, boys or kids. */
    public function sizePresets(): array
    {
        $presets = config($this->isForKids() ? 'marketplace.kids_size_presets' : 'marketplace.size_presets');

        return $presets[$this->category_key] ?? [];
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('products.archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('products.archived_at');
    }

    /** Whether listings are moderated; false until the review_status migration has run. */
    public static function usesReview(): bool
    {
        return Schema::hasColumn('products', 'review_status');
    }

    /** What buyers may see and order: switched on by the seller and approved by an admin. */
    public function scopeVisibleToBuyers($query)
    {
        $query->where('products.is_active', true);

        return static::usesReview() ? $query->where('products.review_status', self::REVIEW_APPROVED) : $query;
    }

    public function getReviewStatusAttribute(?string $value): string
    {
        return $value ?? self::REVIEW_APPROVED;
    }

    public function getIsListedAttribute(): bool
    {
        return (bool) $this->is_active && $this->review_status === self::REVIEW_APPROVED;
    }

    public function getIsPendingReviewAttribute(): bool
    {
        return $this->review_status === self::REVIEW_PENDING;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->review_status === self::REVIEW_REJECTED;
    }

    /** Sends the listing (back) to the admin queue. Review fields are never mass assigned, so sellers cannot approve themselves. */
    public function submitForReview(): void
    {
        if (static::usesReview()) {
            $this->forceFill(['review_status' => self::REVIEW_PENDING, 'review_note' => null, 'reviewed_at' => null, 'reviewed_by' => null])->save();
        }
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether buyers have ordered or reviewed this product. Such products can only be archived: deleting them would
     * erase their reviews and unlink past orders.
     */
    public function hasHistory(): bool
    {
        if (\Illuminate\Support\Facades\DB::table('order_items')->where('product_id', $this->id)->exists()) {
            return true;
        }

        return collect(['product_reviews', 'reviews'])->contains(fn ($table) => Schema::hasTable($table)
            && Schema::hasColumn($table, 'product_id')
            && \Illuminate\Support\Facades\DB::table($table)->where('product_id', $this->id)->exists());
    }

    /** Gallery photos besides the main one, leaving out color photos ("<product> — <color>" alt text). */
    public function getExtraPhotosAttribute()
    {
        $cover = $this->image_url;

        return $this->images->reject(fn (ProductImage $image) => $image->path === $cover
            || ($image->alt_text !== $this->name && Str::contains((string) $image->alt_text, ' — ')))->values();
    }

    /**
     * True when nothing can be bought. Uses the catalog's preloaded variant totals when present to avoid extra queries.
     */
    public function getIsSoldOutAttribute(): bool
    {
        return $this->available_stock <= 0;
    }

    /** How many can be bought across its active options (shown on shop cards). Same sources as is_sold_out. */
    public function getAvailableStockAttribute(): int
    {
        if (array_key_exists('catalog_variant_stock', $this->attributes) && (int) ($this->attributes['active_variants_count'] ?? 0) > 0) {
            return max(0, (int) $this->attributes['catalog_variant_stock']);
        }
        if (static::usesLegacySchema()) {
            // Kept equal to the variant total by syncStockFromVariants().
            return max(0, (int) ($this->attributes['stock'] ?? 0));
        }

        return max(0, (int) $this->variants->where('is_active', true)->sum('stock'));
    }

    /**
     * On the deployed schema products.stock drives catalog listings; keep it equal to the sum of its variants.
     */
    public static function syncStockFromVariants(int $productId): void
    {
        if (! static::usesLegacySchema()) {
            return;
        }

        $variants = ProductVariant::where('product_id', $productId)->where('is_active', true);
        if ((clone $variants)->exists()) {
            static::whereKey($productId)->update(['stock' => (int) (clone $variants)->sum('stock')]);
        }
    }

    public static function usesLegacySchema(): bool
    {
        return Schema::hasColumn('products', 'price');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
