<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['category_name', 'description', 'image'];



    public function products()
    {
        return $this->hasMany(Product::class);
    }

        
    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    public function getProductsStockAttribute()
    {
        return $this->products()->sum('stock');
    }
}




