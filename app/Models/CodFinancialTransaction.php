<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable financial event in a COD parcel's lifecycle. */
class CodFinancialTransaction extends Model
{
    public const BUYER_COLLECTION = 'buyer_collection';

    public const RIDER_COMMISSION = 'rider_commission';

    public const RIDER_TO_LOGISTICS = 'rider_to_logistics';

    public const LOGISTICS_COMMISSION = 'logistics_commission';

    public const ADMIN_COMMISSION = 'admin_commission';

    public const SELLER_PAYABLE = 'seller_payable';

    public const SELLER_SETTLEMENT = 'seller_settlement';

    public const ADMIN_SETTLEMENT = 'admin_settlement';

    protected $fillable = [
        'order_id', 'shipment_id', 'logistics_provider_id', 'type', 'status', 'amount', 'from_user_id', 'to_user_id',
        'recorded_by', 'occurred_at', 'metadata',
    ];

    protected $casts = ['amount' => 'float', 'occurred_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        // Financial entries are append-only. A correction must be a new, auditable event.
        static::updating(fn () => throw new \LogicException('COD financial transactions are immutable.'));
        static::deleting(fn () => throw new \LogicException('COD financial transactions cannot be deleted.'));
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
