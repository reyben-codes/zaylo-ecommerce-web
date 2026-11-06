<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One payment from ZAYLO to a logistics partner for a batch of delivered parcels (see App\Services\LogisticsPayouts). */
class LogisticsPayout extends Model
{
    protected $fillable = ['logistics_provider_id', 'parcels', 'shipping_fees', 'amount', 'partner_share', 'rider_commissions', 'reference', 'paid_by', 'paid_at'];

    protected $casts = ['shipping_fees' => 'float', 'amount' => 'float', 'partner_share' => 'float', 'rider_commissions' => 'float', 'parcels' => 'integer', 'paid_at' => 'datetime'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
