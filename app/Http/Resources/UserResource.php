<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->name,
            'image' => $this->image,
            'role' => $this->role,
            'last_name' => $this->last_name,
           'phone'=>$this->phone,
           

        
        ];
    }
}
