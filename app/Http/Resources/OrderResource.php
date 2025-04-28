<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'date' => $this->date->toDateString(),
            'status' => $this->last_status,
            'delivery_status' => $this->delivery->status ?? null,
            'notes' => $this->notes,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,

                ];
            }),
            'shipping_address' => [
                'address' => $this->user->shippingAddress->address?? '',
                'city' => $this->user->shippingAddress->city?? '',
                'state' => $this->user->shippingAddress->state ?? '',
                'zip_code' => $this->user->shippingAddress->zip_code ?? '',
                'country' => $this->user->shippingAddress->country?? '',

            ],
            'total' => $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        }),
            'created_at' => $this->created_at->toTimeString(),
        ];
    }
}
