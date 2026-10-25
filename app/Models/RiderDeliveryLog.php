<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing a rider did with a parcel: accepted, declined, failed (with a reason), out for delivery, delivered. */
class RiderDeliveryLog extends Model
{
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const FAILED = 'failed';
    public const DELIVERED = 'delivered';

    protected $fillable = ['shipment_id', 'user_id', 'action', 'reason', 'note'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
