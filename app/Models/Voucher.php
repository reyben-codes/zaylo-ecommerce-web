<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A checkout voucher. free_shipping is global and paid by ZAYLO; percent and fixed discounts apply only to the items
 * of shops that joined (voucher_sellers) and are paid by those shops (App\Services\Vouchers).
 */
class Voucher extends Model
{
    public const TYPES = [
        'free_shipping' => 'Free shipping (all shops, paid by ZAYLO)',
        'percent' => '% off (shops that join pay for it)',
        'fixed' => '₱ off (shops that join pay for it)',
    ];

    protected $fillable = [
        'code', 'type', 'is_active', 'starts_at', 'expires_at', 'usage_limit', 'used_count',
        'name', 'value', 'min_spend', 'max_discount', 'funded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'value' => 'float',
        'min_spend' => 'float',
        'max_discount' => 'float',
    ];

    public function sellers(): HasMany
    {
        return $this->hasMany(VoucherSeller::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->expires_at === null || $this->expires_at->gte(now()))
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    public function isFreeShipping(): bool
    {
        return $this->type === 'free_shipping';
    }

    /** Seller-funded discounts need each shop to join first. */
    public function needsOptIn(): bool
    {
        return ! $this->isFreeShipping();
    }

    /** "10% off, up to ₱100, min. spend ₱500" */
    public function describe(): string
    {
        $offer = match ($this->type) {
            'free_shipping' => 'Free shipping',
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off',
            default => '₱'.number_format((float) $this->value, 2).' off',
        };
        if ($this->type === 'percent' && $this->max_discount) {
            $offer .= ', up to ₱'.number_format($this->max_discount, 2);
        }
        if ($this->min_spend) {
            $offer .= ', min. spend ₱'.number_format($this->min_spend, 2);
        }

        return $offer;
    }
}
