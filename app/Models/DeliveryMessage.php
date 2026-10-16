<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message between a shipment's rider and its buyer. */
class DeliveryMessage extends Model
{
    protected $fillable = ['shipment_id', 'sender_id', 'from_rider', 'client_id', 'body', 'read_at'];

    protected $casts = ['from_rider' => 'boolean', 'read_at' => 'datetime'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
