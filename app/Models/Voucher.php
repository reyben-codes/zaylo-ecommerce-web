<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = ['code', 'type', 'is_active', 'starts_at', 'expires_at', 'usage_limit', 'used_count'];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isAvailable(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->expires_at === null || $this->expires_at->gte(now()))
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }
}
