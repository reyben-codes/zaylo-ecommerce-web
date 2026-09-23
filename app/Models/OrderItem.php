<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'seller_id', 'sku', 'unit_price', 'line_total',
        'seller_order_id', 'product_id', 'product_variant_id', 'product_name',
        'variant_name', 'unit_price_minor', 'quantity',
    ];

    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getUnitPriceAttribute(mixed $value): float { return Order::usesLegacySchema() ? (float) $value : ((int) $this->unit_price_minor / 100); }
    public function getLineTotalAttribute(mixed $value): float { return Order::usesLegacySchema() ? (float) $value : (((int) $this->unit_price_minor * $this->quantity) / 100); }
}
