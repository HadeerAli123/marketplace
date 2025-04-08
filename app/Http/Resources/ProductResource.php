<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'product_name' => $this->product_name,
            'price' => $this->is_spot_mode ? $this->price : $this->price,   
            'description' => $this->description,
            'stock' => $this->stock,
            'cover_image' => $this->cover_image,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => new UserResource($this->whenLoaded('user')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
