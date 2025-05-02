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
        $orders = Order::where('last_status', 'processing')
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
            ->whereHas('order', function ($query) {
                $query->whereIn('last_status', ['processing', 'shipped']); 
            })
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


    public function acceptOrder(Order $order)
    {
        if ($order->delivery) {
            return response()->json([
                'status' => false,
                'message' => 'Order is already assigned to a delivery driver.',
            ], 400);
        }

        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => auth()->id(),
            'delivery_time'=> Carbon::now()->addHours(1),
            'address' => $order->user->address ?? null, 
        ]);

        $order->update([
            'last_status' => 'shipped', 
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order accepted successfully.',
        ]);
    }


    public function cancelAcceptance($orderId)
    {
        $delivery = Delivery::where('order_id', $orderId)
            ->where('driver_id', auth()->id())
            ->first();

        if (!$delivery) {
            return response()->json(['status' => false, 'message' => 'Delivery not found or not assigned to you'], 404);
        }

        $delivery->delete();
        
        return response()->json(['status' => true, 'message' => 'Order acceptance has been cancelled']);
    }

}
