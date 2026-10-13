<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsProvider extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'status', 'rejection_reason', 'contact_phone', 'service_area', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Partners riders may apply to: approved by an administrator and owned by an active account.
     */
    public function scopeAcceptingRiders($query)
    {
        return $query->where('status', 'approved')
            ->whereHas('owner', fn ($owner) => $owner->where('status', 'active'))
            ->orderBy('name');
    }
}
