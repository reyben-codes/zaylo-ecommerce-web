<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Wallet credits a rider or logistics partner sent to GCash (simulated; see App\Services\Wallet). */
class WalletCashout extends Model
{
    protected $fillable = ['user_id', 'amount', 'gcash_number', 'gcash_name', 'reference', 'status'];

    protected $casts = ['amount' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 0917 ••• 4567: enough for the owner to recognise the number without showing all of it. */
    public function getMaskedNumberAttribute(): string
    {
        return substr($this->gcash_number, 0, 4).' ••• '.substr($this->gcash_number, -4);
    }
}
