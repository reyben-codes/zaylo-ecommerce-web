<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'tracking_number', 'status', 'picked_up_at', 'delivered_at',
    ];

    protected $casts = ['picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
