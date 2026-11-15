<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWithdrawal extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['processed_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function getAmountAttribute(): float { return $this->amount_minor / 100; }
    public function getGcashNameAttribute(): string { return $this->account_name; }
    public function getMaskedNumberAttribute(): string { return substr($this->account_number, 0, 4).' ••• '.substr($this->account_number, -4); }
}
