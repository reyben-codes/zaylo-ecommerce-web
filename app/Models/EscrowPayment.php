<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscrowPayment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['captured_at' => 'datetime', 'released_at' => 'datetime', 'livemode' => 'boolean'];

    public function order() { return $this->belongsTo(Order::class); }
}
