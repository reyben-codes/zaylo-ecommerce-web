<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One day's time in and time out for a rider, recorded by scanning the partner's attendance QR code. */
class RiderAttendance extends Model
{
    protected $fillable = [
        'rider_id', 'logistics_provider_id', 'work_date', 'time_in_at', 'time_out_at', 'scheduled_start', 'scheduled_end',
    ];

    protected $casts = ['work_date' => 'date', 'time_in_at' => 'datetime', 'time_out_at' => 'datetime'];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }
}
