<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\OrderResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UsersAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ImageService;
use Illuminate\Support\Facades\Validator;

class DeliveryController extends Controller
{
    public function getAvailableOrdersForDelivery()
    {
        $orders = Order::where('last_status', 'pending')
            ->whereDoesntHave('delivery')
            ->get();

        return response()->json([
            'status' => true,
            'orders' => OrderResource::collection($orders)
        ]);
    }



    public function getMyDeliveries()
    {
        $deliveries = Delivery::where('driver_id', auth()->id())  
            ->whereIn('status', ['new', 'in_progress']) 
            ->with('order') 
            ->get();

        $orders = $deliveries->map(function($delivery) {
            return new OrderResource($delivery->order);
        });

        return response()->json([
            'status' => true,
            'orders' => $orders
        ]);
    }


    public function show($id)
    {
        $order = Order::with(['delivery', 'products', 'user'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'order' => new OrderResource($order)
        ]);
}

}
