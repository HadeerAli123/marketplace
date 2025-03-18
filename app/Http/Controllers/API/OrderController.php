<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\User;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class OrderController extends Controller
{
   
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

    }
    

    public function adminOrderDetails(Request $request)
    {
       
        if ($user->role != 'admin') {
            return response()->json(['status' => 'error', 'message' => 'You are not an admin'], 403);
        }

        $date = $request->input('date', now()->toDateString());

        $orders = DB::table('orders')
            ->select('orders.id as order_id', 'orders.date as order_date', 'orders.last_status as status', 
                     'users.first_name as customer_first_name', 'users.last_name as customer_last_name',
                     'deliveries.driver_id')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->where('orders.date', $date)
            ->where('orders.last_status', '!=', 'canceled')
            ->get();

        $orderDetails = [];
        foreach ($orders as $order) {
            $driver = DB::table('users')
                ->select('first_name', 'last_name')
                ->where('id', $order->driver_id)
                ->first();
            $driverName = $driver ? $driver->first_name . ' ' . ($driver->last_name ?? '') : 'not assigned';

            $items = DB::table('order_items')
                ->select('order_items.quantity', 'order_items.price', 'products.name as product_name')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('order_items.order_id', $order->order_id)
                ->get();

            $productNames = $items->pluck('product_name')->implode(', ');

            $totalPrice = $items->sum(function ($item) {
                return $item->quantity * $item->price;
            });

            $orderDetails[] = [
                'order_id' => $order->order_id,
                'customer_name' => $order->customer_first_name . ' ' . ($order->customer_last_name ?? ''),
                'driver_name' => $driverName,
                'order_date' => $order->order_date,
                'product_names' => $productNames,
                'total_price' => $totalPrice,
                'status' => $order->status,
            ];
        }

        return response()->json([
            'status' => 'success',
            'date' => $date,
            'order_details' => $orderDetails,
        ], 200);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function createOrder(Request $request)
    {
        $userId = auth()->id();
        $items = $request->input('items');
        $currentTime = now();
        $orderDate = $currentTime->hour >= 6 ? $currentTime->toDateString() : $currentTime->subDay()->toDateString();
    
        $isSpotModeActive = SpotMode::isActive();
        $spotMode = $isSpotModeActive ? SpotMode::where('status', 'active')->first() : null;
        $sale = $spotMode ? $spotMode->sale : 0;
    
        $order = Order::create([
            'user_id' => $userId,
            'last_status' => 'pending',
            'date' => $orderDate,
        ]);
    
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $basePrice = $product->price;
            $price = $isSpotModeActive ? ($basePrice - ($basePrice * $sale / 100)) : null;
    
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $price,
            ]);
        }
    
        return response()->json(['message' => 'Order created', 'order_id' => $order->id], 201);
    }


    public function updateOrder(Request $request, $orderId)
{
    $order = Order::findOrFail($orderId);
    if ($order->last_status !== 'pending') {
        return response()->json(['error' => 'Cannot update non-pending order'], 403);
    }

    $items = $request->input('items');
    $isSpotModeActive = SpotMode::isActive();
    $spotMode = $isSpotModeActive ? SpotMode::where('status', 'active')->first() : null;
    $sale = $spotMode ? $spotMode->sale : 0;

    foreach ($items as $item) {
        $orderItem = OrderItem::where('order_id', $orderId)
                              ->where('product_id', $item['product_id'])
                              ->first();
        if ($orderItem) {
            $product = Product::find($item['product_id']);
            $basePrice = $product->price;
            $price = $isSpotModeActive ? ($basePrice - ($basePrice * $sale / 100)) : $orderItem->price;

            $orderItem->update([
                'quantity' => $item['quantity'],
                'price' => $price,
            ]);
        }
    }

    return response()->json(['message' => 'Order updated']);
}

public function confirmOrder($orderId)
{
    $order = Order::findOrFail($orderId);
    if ($order->last_status !== 'pending') {
        return response()->json(['error' => 'Order already processed'], 403);
    }

    $isSpotModeActive = SpotMode::isActive();
    $items = $order->orderItems;

    foreach ($items as $item) {
        if (!$item->price && !$isSpotModeActive) {
            return response()->json(['error' => 'Price confirmation required'], 400);
        }
    }

    $order->update(['last_status' => 'processing']);
    $this->notifyDriver($order);

    return response()->json(['message' => 'Order confirmed']);
}


public function assignOrdersToDriver()
{
    $orders = Order::where('last_status', 'processing')->with('orderItems.product', 'user')->get();
    $summary = [];

    foreach ($orders as $order) {
        foreach ($order->orderItems as $item) {
            $productId = $item->product_id;
            $summary[$productId]['quantity'] = ($summary[$productId]['quantity'] ?? 0) + $item->quantity;
            $summary[$productId]['name'] = $item->product->name;
            $summary[$productId]['customers'][] = [
                'name' => $order->user->name,
                'address' => $order->user->address,
                'phone' => $order->user->phone,
                'quantity' => $item->quantity,
            ];
        }
        $order->update(['last_status' => 'shipped']);
    }

    $this->sendDriverNotification($summary);

    return response()->json(['message' => 'Orders assigned to driver', 'summary' => $summary]);
}
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
