<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rider's request to move wallet money to GCash; their logistics partner sends it and records the reference (App\Services\RiderWallet). */
class RiderWithdrawal extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::PENDING => 'Processing',
        self::PAID => 'Sent to GCash',
        self::REJECTED => 'Rejected',
    ];

    protected $fillable = ['user_id', 'logistics_provider_id', 'amount', 'gcash_number', 'gcash_name', 'status', 'reference', 'rejection_reason', 'processed_by', 'processed_at'];

    protected $casts = ['amount' => 'float', 'processed_at' => 'datetime'];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** The number as 0917 123 4567. */
    public function formattedNumber(): string
    {
        return preg_replace('/^(\d{4})(\d{3})(\d{4})$/', '$1 $2 $3', $this->gcash_number) ?? $this->gcash_number;
    }
}
