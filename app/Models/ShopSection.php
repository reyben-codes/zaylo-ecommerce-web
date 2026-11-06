<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

/**
 * A grouping a seller makes for their own shop page, such as "New Arrivals" or "Bundle Deals". Unlike categories it
 * only appears on that shop. shop_id is the value products.seller_id holds (App\Support\Shop::$id).
 */
class ShopSection extends Model
{
    /** Sections per shop, so the tab row stays usable. */
    public const MAX_PER_SHOP = 20;

    public const NAME_MAX = 30;

    protected $fillable = ['shop_id', 'name', 'position', 'is_visible'];

    protected $casts = ['is_visible' => 'boolean', 'position' => 'integer', 'shop_id' => 'integer'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId)->orderBy('position')->orderBy('id');
    }

    /** False until the migration has run, so pages keep working on a database without the tables. */
    public static function available(): bool
    {
        static $available;

        return $available ??= Schema::hasTable('shop_sections') && Schema::hasTable('product_shop_section');
    }
}
