<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['last_status', 'date', 'user_id','notes'];




    public function user()
    {
        return $this->belongsTo(User::class);
    }

protected $casts = [
    'date' => 'date',
];


public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                    ->withPivot('quantity', 'price');
    }
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }


}
