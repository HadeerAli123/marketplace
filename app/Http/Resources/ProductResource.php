<?php

namespace App\Http\Resources;

use App\Models\SpotMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSpotModeActive = SpotMode::isActive();

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,

            'price' => $this->is_spot_mode ? $this->price : $this->price,   

            'price' => $isSpotModeActive ? $this->price : 'Price to be confirmed later',

            'description' => $this->description,
            'stock' => $this->stock,
           'image' => asset('uploads/products/' . $this->image),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => new UserResource($this->whenLoaded('user')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}