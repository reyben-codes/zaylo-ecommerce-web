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
    ];

    protected $casts = ['is_active' => 'boolean', 'sale_value' => 'decimal:2', 'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'sale_variant_id' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->name).'-'.Str::lower(Str::random(6));
            if (static::usesLegacySchema()) {
                $product->sku ??= 'ZAY-'.Str::upper(Str::random(8));
            }
        });

        static::saved(function (Product $product): void {
            if (static::usesLegacySchema()) {
                return;
            }

            if ($product->variantInput !== []) {
                $variant = $product->variants()->oldest('id')->first() ?? new ProductVariant(['product_id' => $product->id]);
                $variant->fill([
                    'sku' => $product->variantInput['sku'] ?? 'ZAY-'.Str::upper(Str::random(8)),
                    'name' => $product->variantInput['variant_name'] ?? 'Default',
                    'options' => $product->variantInput['options'] ?? null,
                    'price_minor' => $product->variantInput['price_minor'] ?? 0,
                    'original_price_minor' => $product->variantInput['original_price_minor'] ?? null,
                    'stock' => $product->variantInput['stock'] ?? 0,
                    'low_stock_threshold' => $product->variantInput['low_stock_threshold'] ?? 5,
                    'weight_grams' => $product->variantInput['weight_grams'] ?? null,
                    'is_active' => $product->is_active ?? true,
                ])->save();
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
            $shop = $user->sellers()->firstOrCreate([], [
                'name' => $user->name."'s Store",
                'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(6)),
                'status' => $user->status === 'active' ? 'approved' : 'pending',
            ]);
            $this->attributes['seller_id'] = $shop->id;
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
            && $now->gte($this->sale_starts_at) && $now->lt($this->sale_ends_at);
    }

    public function getSaleStatusAttribute(): string
    {
        if (! $this->sale_type) return 'No scheduled sale';
        if (now()->lt($this->sale_starts_at)) return 'Scheduled';
        return $this->saleIsActive() ? 'On sale' : 'Ended';
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
        return "CASE WHEN sale_type IS NOT NULL AND sale_starts_at <= ? AND sale_ends_at > ? AND (sale_variant_id IS NULL OR sale_variant_id = {$variant}) THEN CASE WHEN sale_type = 'percent' THEN ROUND({$regular} * (100 - sale_value) / 100, 2) WHEN sale_value < {$regular} THEN sale_value ELSE {$regular} END ELSE {$regular} END";
    }
    public function setStockAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['stock'] = (int) $value : $this->variantInput['stock'] = (int) $value; }
    public function getStockAttribute(mixed $value): int { return static::usesLegacySchema() ? (int) $value : (int) ($this->defaultVariant?->stock ?? 0); }
    public function setSkuAttribute(?string $value): void { static::usesLegacySchema() ? $this->attributes['sku'] = ($value ?: 'ZAY-'.Str::upper(Str::random(8))) : $this->variantInput['sku'] = ($value ?: 'ZAY-'.Str::upper(Str::random(8))); }
    public function getSkuAttribute(mixed $value): ?string { return static::usesLegacySchema() ? $value : $this->defaultVariant?->sku; }
    public function setLowStockThresholdAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['low_stock_threshold'] = (int) $value : $this->variantInput['low_stock_threshold'] = (int) $value; }
    public function getLowStockThresholdAttribute(mixed $value): int { return static::usesLegacySchema() ? (int) ($value ?? 5) : (int) ($this->defaultVariant?->low_stock_threshold ?? 5); }
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->oldestOfMany();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy(static::usesLegacySchema() ? 'sort_order' : 'position');
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
