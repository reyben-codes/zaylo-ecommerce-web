<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt_text', 'position', 'sort_order'];

    public function getSortOrderAttribute(): int
    {
        return (int) (Schema::hasColumn('product_images', 'sort_order')
            ? ($this->attributes['sort_order'] ?? 0)
            : ($this->attributes['position'] ?? 0));
    }

    public function setSortOrderAttribute(int $value): void
    {
        $this->attributes[Schema::hasColumn('product_images', 'sort_order') ? 'sort_order' : 'position'] = $value;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
