<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a rider earned for delivering one parcel (see App\Services\RiderWallet). */
class RiderCommission extends Model
{
    protected $fillable = ['user_id', 'shipment_id', 'logistics_provider_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
