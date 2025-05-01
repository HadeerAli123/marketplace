<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['order_id', 'driver_id', 'status', 'delivery_time','address','delivery_fee'];

    protected $casts = [
        'delivery_time' => 'datetime',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

}
