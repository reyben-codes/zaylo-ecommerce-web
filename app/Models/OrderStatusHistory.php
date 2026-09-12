<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = ['order_id', 'changed_by', 'status', 'note'];
}
