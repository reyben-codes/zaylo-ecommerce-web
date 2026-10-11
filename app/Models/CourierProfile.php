<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved rider's working profile. Created from their RiderApplication
 * when logistics approves it.
 */
class CourierProfile extends Model
{
    protected $fillable = ['user_id', 'vehicle_type', 'license_number', 'verification_notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
