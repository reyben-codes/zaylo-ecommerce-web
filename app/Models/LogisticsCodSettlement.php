<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One verified COD parcel allocated by its logistics partner. */
class LogisticsCodSettlement extends Model
{
    public const AWAITING_SELLER_TRANSFER = 'awaiting_seller_transfer';

    public const SETTLED = 'settled';

    protected $fillable = [
        'shipment_id', 'rider_cash_handover_id', 'logistics_provider_id', 'order_id', 'seller_user_id',
        'product_price', 'amount_received', 'rider_earnings', 'logistics_earnings', 'admin_shipping_earnings', 'admin_product_commission',
        'admin_earnings', 'seller_earnings', 'status',
        'seller_reference', 'recorded_by', 'settled_by', 'settled_at', 'settlement_breakdown', 'admin_reference', 'admin_settled_at', 'admin_settled_by',
    ];

    protected $casts = [
        'product_price' => 'float', 'amount_received' => 'float', 'rider_earnings' => 'float', 'logistics_earnings' => 'float',
        'admin_shipping_earnings' => 'float', 'admin_product_commission' => 'float', 'admin_earnings' => 'float',
        'seller_earnings' => 'float', 'settled_at' => 'datetime', 'settlement_breakdown' => 'array', 'admin_settled_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(RiderCashHandover::class, 'rider_cash_handover_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
