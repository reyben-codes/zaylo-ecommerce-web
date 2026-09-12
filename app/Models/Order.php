<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'seller_id', 'status', 'subtotal', 'shipping_fee',
        'total', 'currency', 'payment_method', 'recipient_name', 'phone',
        'shipping_address', 'notes', 'placed_at', 'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'total' => 'decimal:2',
        'placed_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
