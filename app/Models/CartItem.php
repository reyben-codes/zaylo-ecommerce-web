<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity', 'selected'];

    protected $casts = ['selected' => 'boolean'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return Schema::hasColumn('cart_items', 'product_id')
            ? $this->belongsTo(Product::class)
            : $this->hasOneThrough(Product::class, ProductVariant::class, 'id', 'id', 'product_variant_id', 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unitPrice(): float
    {
        return (float) ($this->variant?->price ?? $this->product->price);
    }

    public function availableStock(): int
    {
        return (int) ($this->variant?->stock ?? $this->product->stock);
    }
}
