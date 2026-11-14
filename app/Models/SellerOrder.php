<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id', 'seller_id', 'logistics_provider_id', 'subtotal_minor',
        'shipping_fee_minor', 'commission_minor', 'status', 'delivered_at', 'note', 'settlement_breakdown',
    ];

    protected $casts = ['delivered_at' => 'datetime', 'settlement_breakdown' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if ($order->settlement_breakdown === null && \Illuminate\Support\Facades\Schema::hasColumn('seller_orders', 'settlement_breakdown')) {
                $order->settlement_breakdown = \App\Services\Settlement::deliveryBreakdown(
                    (int) $order->subtotal_minor / 100,
                    (int) $order->shipping_fee_minor / 100,
                    riderRate: $order->provider?->riderRate() ?? 40,
                );
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
