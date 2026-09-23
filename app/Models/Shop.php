<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'logo',
        'cover_image',
        'tagline',
        'description',
        'featured',
        'is_active',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id', 'seller_id');
    }
}