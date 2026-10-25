<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer following a shop (see App\Support\Shop for what shop_id means). */
class ShopFollow extends Model
{
    protected $fillable = ['user_id', 'shop_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
