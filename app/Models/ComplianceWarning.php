<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A warning the admin gave a seller, optionally about one product, and what was done about it.
 */
class ComplianceWarning extends Model
{
    public const REASONS = [
        'wrong_category' => "Outside the seller's registered category",
        'prohibited' => 'Prohibited item',
        'inappropriate' => 'Inappropriate content or images',
        'misleading' => 'Misleading price or description',
        'other' => 'Other policy violation',
    ];

    public const ACTIONS = [
        'warning' => 'Warning only',
        'product_hidden' => 'Warning, product hidden',
        'suspended' => 'Seller suspended',
    ];

    protected $fillable = ['seller_id', 'product_id', 'issued_by', 'reason', 'details', 'action', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? 'Policy violation';
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? 'Warning only';
    }
}
