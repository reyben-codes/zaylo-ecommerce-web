<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shop's answer to a voucher invitation: pending, accepted (the voucher applies to its items) or declined. */
class VoucherSeller extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected $fillable = ['voucher_id', 'seller_id', 'status', 'responded_at'];

    protected $casts = ['responded_at' => 'datetime'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
