<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'name', 'size', 'color', 'price', 'stock', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
