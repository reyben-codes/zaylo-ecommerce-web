<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderLegCommission extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'float'];
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
