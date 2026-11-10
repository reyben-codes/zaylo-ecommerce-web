<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cash a rider handed to the hub for the COD parcels they delivered, with any shortfall (App\Services\CashHandovers). */
class RiderCashHandover extends Model
{
    protected $fillable = ['logistics_provider_id', 'rider_user_id', 'parcels', 'expected', 'received', 'shortfall', 'note', 'recorded_by'];

    protected $casts = ['expected' => 'float', 'received' => 'float', 'shortfall' => 'float', 'parcels' => 'integer'];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(LogisticsCodSettlement::class);
    }
}
