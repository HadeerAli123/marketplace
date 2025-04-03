<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
        'category_name' => $this->category_name,
        'description' => $this->description,
        'image' => $this->image ? asset($this->image) : null, 
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'products_count' => $this->products_count,
        'products_sum_stock' => $this->products_sum_stock,
        ];
    }
    
}
