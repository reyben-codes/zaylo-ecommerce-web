<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProductReview extends Model
{
    protected $fillable = ['order_item_id', 'product_id', 'user_id', 'rating', 'comment', 'photos'];
    protected $casts = ['photos' => 'array', 'rating' => 'integer'];

    private static ?bool $opensToEveryone = null;

    public function buyer() { return $this->belongsTo(User::class, 'user_id'); }
    public function product() { return $this->belongsTo(Product::class); }

    /** Written about a delivered order item; reviews from the product page have none. */
    public function isVerifiedPurchase(): bool
    {
        return $this->order_item_id !== null;
    }

    /**
     * Whether buyers can review products they have not bought: after the 2026_11_03 migration, which Render does not
     * run on deploy. Until then the product page keeps only purchase reviews.
     */
    public static function opensToEveryone(): bool
    {
        return self::$opensToEveryone ??= Schema::hasIndex('product_reviews', ['product_id', 'user_id']);
    }
}
