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
            'full_name' => $this->first_name . ' ' . $this->last_name,
            // 'first_name' => $this->first_name,
            // 'last_name' => $this->last_name,
            'image' => $this->image,
            'role' => $this->role,
            'last_name' => $this->last_name,
           'phone'=>$this->phone,
           'username'=>$this->username,
           'addresses' => $this->addresses,
           'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status' => $this->status,
           

        
        ];
    }
}
