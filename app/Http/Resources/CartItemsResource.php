<?php

namespace App\Http\Resources;

use App\Models\SpotMode;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemsResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->product;
        $isSpotModeActive = SpotMode::isActive();

        $price = $isSpotModeActive ? $product->price : $product->regular_price; 

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'price' => $price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
            ],
        ];
    }
}