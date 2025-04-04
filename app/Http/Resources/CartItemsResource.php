<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->product_name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->quantity * $this->price,
            'cover_image' => $this->product->cover_image,
        ];
    }
}
