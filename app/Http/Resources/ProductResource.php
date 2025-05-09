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
            'price' => $isSpotModeActive ? $this->price : $this->regular_price,
            'description' => $this->description,
            'stock' => $this->stock,
            'regular_price' => $this->regular_price,
            'cover_image' => asset('uploads/products/' . $this->cover_image),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => new UserResource($this->whenLoaded('user')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}