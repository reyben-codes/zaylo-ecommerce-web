<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name', 'options', 'price_minor', 'original_price_minor',
        'size', 'color', 'price', 'stock', 'low_stock_threshold', 'weight_grams', 'is_active',
    ];

    protected $casts = ['options' => 'array', 'price' => 'decimal:2', 'is_active' => 'boolean'];

    public function getPriceAttribute(mixed $value): float
    {
        return (float) ($this->product?->priceFor($this) ?? $this->regular_price);
    }

    public function getRegularPriceAttribute(): float
    {
        return Schema::hasColumn('product_variants', 'price')
            ? (float) ($this->attributes['price'] ?? 0)
            : ((int) ($this->attributes['price_minor'] ?? 0)) / 100;
    }

    /** The color this option belongs to: the deployed schema's color column, or options.color on the newer one. */
    public function getColorLabelAttribute(): ?string
    {
        $color = $this->attributes['color'] ?? ($this->options['color'] ?? null);

        return filled($color) ? trim((string) $color) : null;
    }

    public function getSizeLabelAttribute(): ?string
    {
        $size = $this->attributes['size'] ?? ($this->options['size'] ?? null);

        return filled($size) ? trim((string) $size) : null;
    }

    /** Buyer-facing name, e.g. "Olive / US 8", "Olive" or "US 8". */
    public static function nameFor(?string $color, ?string $size): string
    {
        return collect([$color, $size])->filter(fn ($part) => filled($part))->implode(' / ') ?: 'Default';
    }

    /** A swatch color for names containing a common color word ("Navy Blue" → navy), or null. */
    public static function swatchFor(string $color): ?string
    {
        foreach (config('marketplace.color_swatches') as $word => $hex) {
            if (preg_match('/\b'.$word.'\b/i', $color)) {
                return $hex;
            }
        }

        return null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
