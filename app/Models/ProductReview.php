<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['order_item_id', 'product_id', 'user_id', 'rating', 'comment', 'photos'];
    protected $casts = ['photos' => 'array', 'rating' => 'integer'];
    public function buyer() { return $this->belongsTo(User::class, 'user_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
