<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'seller_id', 'product_name',
        'sku', 'unit_price', 'quantity', 'line_total',
    ];

    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
