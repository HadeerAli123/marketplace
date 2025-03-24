<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\SpotMode;
use App\Models\UsersAddress;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\User;
use App\Models\Cart;
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
        $userId = auth()->id();
    
        $orders = Order::where('user_id', $userId)
                       ->select('id', 'date', 'last_status', 'total_price')
                       ->with('orderItems.product:name,id')
                       ->get();
    
        $orderDetails = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'date' => $order->date,
                'status' => $order->last_status,
                'total_price' => $order->total_price,
                'products' => $order->orderItems->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ];
        });
    
        return response()->json([
            'status' => 'success',
            'orders' => $orderDetails,
        ], 200);
    }

    public function adminOrderDetails(Request $request)
    {
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
    public function createOrder()
    {
        $userId = auth()->id();
    
        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->with('cartItems.product')
                    ->first();

        if (!$cart || $cart->cartItems->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        $shippingAddress = UsersAddress::where('user_id', $userId)
                                      ->where('type', 'shipping')
                                      ->first();

        if (!$shippingAddress) {
            return response()->json(['error' => 'No shipping address found'], 400);
        }

        $currentTime = now();
        $orderDate = $currentTime->hour >= 6 ? $currentTime->toDateString() : $currentTime->subDay()->toDateString();

        $isSpotModeActive = SpotMode::isActive();
        $spotMode = $isSpotModeActive ? SpotMode::where('status', 'active')->first() : SpotMode::where('status', 'not_active')->first();
        $sale = $spotMode ? $spotMode->sale : 0;

        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $userId,
                'last_status' => 'pending',
                'date' => $orderDate,
            ]);

            $totalOrderPrice = 0;

            foreach ($cart->cartItems as $cartItem) {
                $product = $cartItem->product;

                if ($product->stock <= 0) {
                    throw new \Exception('Product ' . $product->name . ' is out of stock');
                }

                if ($cartItem->quantity > $product->stock) {
                    throw new \Exception('Requested quantity for ' . $product->name . ' exceeds available stock');
                }

                $basePrice = $product->price;
                $price = $isSpotModeActive ? max(0, $basePrice - ($basePrice * $sale / 100)) : $basePrice;
                $totalPrice = $price * $cartItem->quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $price,
                    'total_price' => $totalPrice,
                ]);

                $totalOrderPrice += $totalPrice;

                $product->stock -= $cartItem->quantity;
                $product->save();
            }

            $order->update(['total_price' => $totalOrderPrice]);

            $cart->cartItems()->delete();
            $cart->delete();

            Delivery::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'address' => $shippingAddress->address,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
                'total_price' => $order->total_price,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Checkout failed: ' . $e->getMessage());
            return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    public function updateOrder(Request $request, $orderId)
    {
        $order = Order::where('id', $orderId)
                      ->where('user_id', auth()->id())
                      ->with('orderItems.product')
                      ->firstOrFail();

        if ($order->last_status !== 'pending') {
            return response()->json(['error' => 'Cannot update non-pending order'], 403);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $isSpotModeActive = SpotMode::isActive();
        $spotMode = $isSpotModeActive ? SpotMode::where('status', 'active')->first() : SpotMode::where('status', 'not_active')->first();
        $sale = $spotMode ? $spotMode->sale : 0;

        DB::beginTransaction();

        try {
            $totalPrice = 0;

            foreach ($items as $item) {
                $orderItem = OrderItem::where('order_id', $orderId)
                                      ->where('product_id', $item['product_id'])
                                      ->first();

                if (!$orderItem) {
                    // المنتج مش موجود في الطلب، بنرجع خطأ
                    throw new \Exception('Product with ID ' . $item['product_id'] . ' not found in this order');
                }

                $product = $orderItem->product;

                if ($product->stock <= 0) {
                    throw new \Exception('Product ' . $product->name . ' is out of stock');
                }

                $quantityDifference = $item['quantity'] - $orderItem->quantity;

                if ($quantityDifference > 0 && $quantityDifference > $product->stock) {
                    throw new \Exception('Requested quantity for ' . $product->name . ' exceeds available stock');
                }

                $basePrice = $product->price;
                $price = $isSpotModeActive ? max(0, $basePrice - ($basePrice * $sale / 100)) : $basePrice;

                $orderItem->update([
                    'quantity' => $item['quantity'],
                    'price' => $price,
                ]);

                $product->stock -= $quantityDifference;
                $product->save();

                $totalPrice += $price * $item['quantity'];
            }

            $order->update(['total_price' => $totalPrice]);

            DB::commit();

            return response()->json([
                'message' => 'Order updated successfully',
                'order_id' => $order->id,
                'total_price' => $order->total_price,
                'items' => $order->orderItems,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order update failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }

    public function confirmOrder($orderId)
    {
        $order = Order::where('id', $orderId)
                      ->where('user_id', auth()->id())
                      ->firstOrFail();
    
        if ($order->last_status !== 'pending') {
            return response()->json(['error' => 'Order already processed'], 403);
        }
    
        DB::beginTransaction();
    
        try {
            $order->update(['last_status' => 'processing']);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Order confirmed successfully',
                'order_id' => $order->id,
                'total_price' => $order->total_price,
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order confirmation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to confirm order: ' . $e->getMessage()], 500);
        }
    }

    public function assignOrdersToDriver(Request $request)
    {
        $orders = Order::where('last_status', 'processing')
                       ->with('orderItems.product', 'user')
                       ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders to assign'], 200);
        }

        $driverId = $request->input('driver_id'); 
        $assignToAll = is_null($driverId); 

        if (!$assignToAll) {
            $driver = User::where('role', 'driver')->find($driverId);
            if (!$driver) {
                return response()->json(['error' => 'Driver not found'], 404);
            }
        }

        $summary = [];

        DB::beginTransaction();

        try {
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

                if (!$assignToAll) {
                    Delivery::create([
                        'order_id' => $order->id,
                        'driver_id' => $driverId,
                        'status' => 'new',
                        'address' => $order->user->address,
                    ]);
                }

                $order->update(['last_status' => 'shipped']);
            }

            if ($assignToAll) {
                $drivers = User::where('role', 'driver')->get();
                foreach ($drivers as $driver) {
                    $this->sendDriverNotification($summary, $driver);
                }
            } else {
                $this->sendDriverNotification($summary, $driver);
            }

            DB::commit();

            return response()->json([
                'message' => 'Orders assigned to driver successfully',
                'summary' => $summary,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order assignment failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to assign orders: ' . $e->getMessage()], 500);
        }
    }

    private function sendDriverNotification($summary, $driver)
    {
        $driver->notify(new DriverOrderAssignedNotification($summary));
    }

    public function acceptOrder(Request $request, $orderId)
    {
        try {
            $driver = Auth::user();
    
            $order = Order::findOrFail($orderId);
            if ($order->last_status !== 'shipped') {
                return response()->json(['error' => 'Order cannot be accepted at this stage'], 403);
            }
    
            $existingDelivery = Delivery::where('order_id', $orderId)->first();
    
            if ($existingDelivery) {
                if ($existingDelivery->driver_id !== $driver->id) {
                    return response()->json(['error' => 'Order already assigned to another driver'], 403);
                }
    
                $existingDelivery->update([
                    'status' => 'in_progress',
                ]);
            } else {
                Delivery::create([
                    'order_id' => $orderId,
                    'driver_id' => $driver->id,
                    'status' => 'in_progress',
                    'address' => $order->user->address,
                ]);
            }
    
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                $admin->notify(new OrderAssignedNotification($order, $driver));
            }
    
            return response()->json(['message' => 'Order accepted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deliverOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->last_status !== 'shipped') {
            return response()->json(['error' => 'Only shipped orders can be delivered'], 403);
        }

        DB::beginTransaction();

        try {
            $order->update(['last_status' => 'delivered']);

            // إشعار لليوزر إن الطلب اتم تسليمه 
            $order->user->notify(new OrderDelivered($order));

            DB::commit();

            return response()->json([
                'message' => 'Order delivered successfully',
                'order_id' => $order->id,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order delivery failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to deliver order: ' . $e->getMessage()], 500);
        }
    }

    public function cancelOrder(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            $userId = Auth::id();
            if ($order->user_id !== $userId) {
                return response()->json(['error' => 'You are not authorized to cancel this order'], 403);
            }

            if ($order->last_status !== 'pending') {
                return response()->json(['error' => 'Cannot cancel order. Only pending orders can be canceled'], 403);
            }

            $order->update([
                'last_status' => 'canceled',
            ]);

            $orderItems = $order->orderItems;
            foreach ($orderItems as $orderItem) {
                $product = $orderItem->product;
                if ($product) {
                    $product->increment('stock', $orderItem->quantity);
                }
            }

            return response()->json(['message' => 'Order canceled successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getDriverOrders()
    {
        $driverId = auth()->id();

        $orders = Order::where('last_status', 'shipped')
                       ->whereHas('delivery', function ($query) use ($driverId) {
                           $query->where('driver_id', $driverId);
                       })
                       ->with('orderItems.product', 'user')
                       ->get();

        $orderDetails = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'customer_name' => $order->user->first_name . ' ' . ($order->user->last_name ?? ''),
                'customer_address' => $order->delivery->address,
                'customer_phone' => $order->user->phone,
                'total_price' => $order->total_price,
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'orders' => $orderDetails,
        ], 200);
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        $userId = auth()->id();
    
        $order = Order::where('id', $id)
                      ->where('user_id', $userId)
                      ->with('orderItems.product', 'delivery')
                      ->firstOrFail();
    
        $orderDetails = [
            'order_id' => $order->id,
            'date' => $order->date,
            'status' => $order->last_status,
            'total_price' => $order->total_price,
            'shipping_address' => $order->delivery->address,
            'items' => $order->orderItems->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->price * $item->quantity,
                ];
            }),
        ];
    
        return response()->json([
            'status' => 'success',
            'order' => $orderDetails,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}