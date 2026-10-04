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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
