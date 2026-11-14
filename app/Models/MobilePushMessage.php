<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePushMessage extends Model
{
    protected $guarded = [];
    protected $casts = ['data' => 'array', 'available_at' => 'datetime', 'locked_until' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    public function device() { return $this->belongsTo(MobilePushDevice::class, 'device_id'); }
}
