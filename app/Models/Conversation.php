<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['buyer_id', 'seller_id', 'product_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    /**
     * The buyer's conversation with the seller of a product, started about that product the first time.
     * Only active, verified sellers can be messaged.
     */
    public static function startAbout(User $buyer, Product $product): self
    {
        abort_unless($product->is_active, 404);
        $seller = Product::usesLegacySchema() ? $product->seller : $product->seller?->owner;
        abort_unless($seller && $seller->isActive() && $seller->hasRole('seller') && $seller->hasVerifiedEmail(), 404);
        abort_if((int) $seller->id === (int) $buyer->id, 422, 'You cannot message yourself.');

        return static::firstOrCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id], ['product_id' => $product->id]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('buyer_id', $userId)->orWhere('seller_id', $userId));
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
