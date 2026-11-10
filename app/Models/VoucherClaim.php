<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer claiming a voucher.
 *
 * If a voucher specifies a usage limit, total claims across buyers are capped
 * at that limit. Once fully claimed, no other buyers can claim it.
 */
class VoucherClaim extends Model
{
    protected $fillable = [
        'voucher_id',
        'user_id',
        'claimed_at',
        'used_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
