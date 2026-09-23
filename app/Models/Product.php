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
    ];

    protected $casts = ['is_active' => 'boolean'];

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
    public function getPriceAttribute(mixed $value): string { return static::usesLegacySchema() ? number_format((float) $value, 2, '.', '') : number_format(($this->defaultVariant?->price_minor ?? 0) / 100, 2, '.', ''); }
    public function setOriginalPriceAttribute(mixed $value): void { static::usesLegacySchema() ? $this->attributes['original_price'] = $value : $this->variantInput['original_price_minor'] = filled($value) ? (int) round((float) $value * 100) : null; }
    public function getOriginalPriceAttribute(mixed $value): ?string { return static::usesLegacySchema() ? (filled($value) ? number_format((float) $value, 2, '.', '') : null) : ($this->defaultVariant?->original_price_minor === null ? null : number_format($this->defaultVariant->original_price_minor / 100, 2, '.', '')); }
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
