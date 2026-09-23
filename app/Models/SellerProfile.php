<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SellerProfile extends Seller
{
    protected $fillable = [
        'user_id',
        'store_name',
        'name',
        'slug',
        'business_registration',
        'verification_notes',
        'status',
    ];

    public function setStoreNameAttribute(string $value): void
    {
        if (Schema::hasTable('seller_profiles')) {
            $this->attributes['store_name'] = $value;
            return;
        }

        $this->attributes['name'] = $value;
        $this->attributes['slug'] ??= \Illuminate\Support\Str::slug($value).'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        $this->attributes['status'] ??= 'approved';
    }

    public function getStoreNameAttribute(): ?string
    {
        return $this->attributes['store_name'] ?? $this->attributes['name'] ?? null;
    }

    public function getTable()
    {
        return Schema::hasTable('seller_profiles') ? 'seller_profiles' : 'sellers';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
