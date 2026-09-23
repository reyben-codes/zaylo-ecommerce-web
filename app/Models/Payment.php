<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'amount_minor', 'provider_ref',
        'provider', 'provider_reference', 'status', 'amount', 'currency',
        'idempotency_key', 'payload', 'paid_at',
    ];

    protected $casts = ['payload' => 'array', 'paid_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getAmountAttribute(mixed $value): float { return Order::usesLegacySchema() ? (float) $value : ((int) $this->amount_minor / 100); }
}
