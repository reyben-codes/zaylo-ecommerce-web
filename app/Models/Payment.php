<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'provider_reference', 'status', 'amount',
        'currency', 'idempotency_key', 'payload', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'payload' => 'array', 'paid_at' => 'datetime'];
}
