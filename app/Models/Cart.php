<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'status','total_price'];

    public function calculateTotalPrice()
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }

        $total = $this->items->sum(fn($item) => $item->quantity * $item->price);

        $total = $total ?: 0;

        $this->update(['total_price' => $total]);

        return $total;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
