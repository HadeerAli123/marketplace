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
    
    /////testok
    
    public function index()
{
    $userId = auth()->id();

    $orders = Order::where('user_id', $userId)
                   ->select('id', 'date', 'last_status')
                   ->with('items.product:product_name,id')
                   ->get();

    $isSpotModeActive = SpotMode::isActive();

    $orderDetails = $orders->map(function ($order) use ($isSpotModeActive) {
        $showPrices = $isSpotModeActive || $order->last_status !== 'awaiting_price_confirmation';

        $orderData = [
            'order_id' => $order->id,
            'date' => $order->date,
            'status' => $order->last_status,
            'products' => $order->items->map(function ($item) use ($showPrices) {
                $productData = [
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                ];

              
                if ($showPrices) {
                    $productData['price'] = $item->price;
                }

                return $productData;
            }),
        ];

       
        if ($showPrices) {
            $orderData['total_price'] = $order->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });
        }

        return $orderData;
    });

    return response()->json([
        'status' => 'success',
        'orders' => $orderDetails,
    ], 200);
}



//////////////test ok
public function createOrder(Request $request)
{
    $userId = auth()->id();

    $cart = Cart::where('user_id', $userId)
                ->whereIn('status', ['pending', 'awaiting_price_confirmation'])
                ->with('items.product')
                ->first();

    if (!$cart || $cart->items->isEmpty()) {
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

    if (!$isSpotModeActive) {
        $request->validate([
            'action' => 'required|in:confirm_later,buy_anyway',
        ]);
    }

    $notes = $request->input('notes', null);

    DB::beginTransaction();

    try {
        if (!$isSpotModeActive) {
     
            if ($request->action === 'confirm_later') {
              
                $cart->update(['status' => 'awaiting_price_confirmation']);
                DB::commit();
                return response()->json([
                    'message' => 'Cart is awaiting price confirmation. Please confirm when Spot Mode is active.',
                ], 200);
            } elseif ($request->action === 'buy_anyway') {
                
                $order = Order::create([
                    'user_id' => $userId,
                    'last_status' => 'awaiting_price_confirmation',
                    'date' => $orderDate,
                    'notes' => $notes,
                ]);

                foreach ($cart->items as $cartItem) {
                    $product = $cartItem->product;

                    if ($product->stock <= 0) {
                        throw new \Exception('Product ' . $product->product_name . ' is out of stock');
                    }

                    if ($cartItem->quantity > $product->stock) {
                        throw new \Exception('Requested quantity for ' . $product->product_name . ' exceeds available stock');
                    }

                    
                    $price = 0;
                    $totalPrice = 0;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $price,
                        'total_price' => $totalPrice,
                    ]);

                    $product->stock -= $cartItem->quantity;
                    $product->save();
                }

                $cart->update(['status' => 'confirmed']);
                DB::commit();

                return response()->json([
                    'message' => 'Order placed successfully, awaiting price confirmation.',
                    'order_id' => $order->id,
                    'status' => $order->last_status,
                    'notes' => $order->notes,
                ], 201);
            }
        } else {
          
            $order = Order::create([
                'user_id' => $userId,
                'last_status' => 'processing',
                'date' => $orderDate,
                'notes' => $notes,
            ]);

            $totalOrderPrice = 0;

            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                if ($product->stock <= 0) {
                    throw new \Exception('Product ' . $product->product_name . ' is out of stock');
                }

                if ($cartItem->quantity > $product->stock) {
                    throw new \Exception('Requested quantity for ' . $product->product_name . ' exceeds available stock');
                }

                if ($product->price === null) {
                    throw new \Exception('Price not available for product: ' . $product->product_name);
                }

                $price = $product->price;
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

            $cart->update(['status' => 'confirmed']);

            Delivery::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'address' => $shippingAddress->address,
                'status' => 'new',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
                'status' => $order->last_status,
                'total_price' => $totalOrderPrice,
                'notes' => $order->notes,
                'products' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ], 201);
        }
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Checkout failed: ' . $e->getMessage());
        return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
    }
}
//////  test ok
public function confirmawaitCart(Request $request)
{
    $userId = auth()->id();

    $cart = Cart::where('user_id', $userId)
                ->where('status', 'awaiting_price_confirmation')
                ->with('items.product')
                ->first();

    if (!$cart || $cart->items->isEmpty()) {
        return response()->json(['error' => 'No cart awaiting price confirmation'], 400);
    }

    if (!SpotMode::isActive()) {
        return response()->json(['error' => 'Spot Mode must be active to confirm the cart'], 400);
    }

    $shippingAddress = UsersAddress::where('user_id', $userId)
                                   ->where('type', 'shipping')
                                   ->first();

    if (!$shippingAddress) {
        return response()->json(['error' => 'No shipping address found'], 400);
    }

    $currentTime = now();
    $orderDate = $currentTime->hour >= 6 ? $currentTime->toDateString() : $currentTime->subDay()->toDateString();

    DB::beginTransaction();

    try {
        $order = Order::create([
            'user_id' => $userId,
            'last_status' => 'processing', 
            'date' => $orderDate,
        ]);

        $totalOrderPrice = 0;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;

            if ($product->stock <= 0) {
                throw new \Exception('Product ' . $product->product_name . ' is out of stock');
            }

            if ($cartItem->quantity > $product->stock) {
                throw new \Exception('Requested quantity for ' . $product->product_name . ' exceeds available stock');
            }

            if ($product->price === null) {
                \Log::warning("Price is null for product ID: {$product->id}");
                throw new \Exception('Price not available for product: ' . $product->product_name);
            }

            $price = $product->price;
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

        $cart->update(['status' => 'confirmed']);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'address' => $shippingAddress->address,
            'status' => 'new',
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Cart confirmed and order created successfully',
            'order_id' => $order->id,
            'total_price' => $totalOrderPrice,
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Cart confirmation failed: ' . $e->getMessage());
        return response()->json(['error' => 'Cart confirmation failed: ' . $e->getMessage()], 500);
    }
}

    // public function updateOrder(Request $request, $orderId)
    // {
    //     $order = Order::where('id', $orderId)
    //                   ->where('user_id', auth()->id())
    //                   ->with('orderItems.product')
    //                   ->firstOrFail();

    //     if ($order->last_status !== 'pending') {
    //         return response()->json(['error' => 'Cannot update non-pending order'], 403);
    //     }

    //     $request->validate([
    //         'items' => 'required|array',
    //         'items.*.product_id' => 'required|exists:products,id',
    //         'items.*.quantity' => 'required|integer|min:1',
    //     ]);

    //     $items = $request->input('items');
    //     $isSpotModeActive = SpotMode::isActive();

    //     DB::beginTransaction();

    //     try {
    //         $totalPrice = 0;

    //         foreach ($items as $item) {
    //             $orderItem = OrderItem::where('order_id', $orderId)
    //                                   ->where('product_id', $item['product_id'])
    //                                   ->first();

    //             if (!$orderItem) {
    //                 throw new \Exception('Product with ID ' . $item['product_id'] . ' not found in this order');
    //             }

    //             $product = $orderItem->product;

    //             if ($product->stock <= 0) {
    //                 throw new \Exception('Product ' . $product->product_name . ' is out of stock');
    //             }

    //             $quantityDifference = $item['quantity'] - $orderItem->quantity;

    //             if ($quantityDifference > 0 && $quantityDifference > $product->stock) {
    //                 throw new \Exception('Requested quantity for ' . $product->product_name . ' exceeds available stock');
    //             }

    //             $price = $isSpotModeActive ? $product->price : $orderItem->price;

    //             $orderItem->update([
    //                 'quantity' => $item['quantity'],
    //                 'price' => $price,
    //             ]);

    //             $product->stock -= $quantityDifference;
    //             $product->save();

    //             $totalPrice += $price * $item['quantity'];
    //         }

    //         $order->update(['total_price' => $totalPrice]);

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Order updated successfully',
    //             'order_id' => $order->id,
    //             'total_price' => $order->total_price,
    //             'items' => $order->orderItems,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Order update failed: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to update order: ' . $e->getMessage()], 500);
    //     }
    // }



    // public function confirmOrder($orderId)
    // {
    //     $order = Order::where('id', $orderId)
    //                   ->where('user_id', auth()->id())
    //                   ->firstOrFail();
    
    //     if ($order->last_status !== 'pending') {
    //         return response()->json(['error' => 'Order already processed'], 403);
    //     }
    
    //     DB::beginTransaction();
    
    //     try {
    //         $order->update(['last_status' => 'processing']);
    
    //         DB::commit();
    
    //         return response()->json([
    //             'message' => 'Order confirmed successfully',
    //             'order_id' => $order->id,
    //             'total_price' => $order->total_price,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Order confirmation failed: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to confirm order: ' . $e->getMessage()], 500);
    //     }
    // }



    // public function assignOrdersToDriver(Request $request)
    // {
    //     $orders = Order::where('last_status', 'processing')
    //                    ->with('orderItems.product', 'user')
    //                    ->get();

    //     if ($orders->isEmpty()) {
    //         return response()->json(['message' => 'No orders to assign'], 200);
    //     }

    //     $driverId = $request->input('driver_id'); 
    //     $assignToAll = is_null($driverId); 

    //     if (!$assignToAll) {
    //         $driver = User::where('role', 'driver')->find($driverId);
    //         if (!$driver) {
    //             return response()->json(['error' => 'Driver not found'], 404);
    //         }
    //     }

    //     $summary = [];

    //     DB::beginTransaction();

    //     try {
    //         foreach ($orders as $order) {
    //             foreach ($order->orderItems as $item) {
    //                 $productId = $item->product_id;
    //                 $summary[$productId]['quantity'] = ($summary[$productId]['quantity'] ?? 0) + $item->quantity;
    //                 $summary[$productId]['name'] = $item->product->product_name;
    //                 $summary[$productId]['customers'][] = [
    //                     'name' => $order->user->name,
    //                     'address' => $order->user->address,
    //                     'phone' => $order->user->phone,
    //                     'quantity' => $item->quantity,
    //                 ];
    //             }

    //             if (!$assignToAll) {
    //                 Delivery::create([
    //                     'order_id' => $order->id,
    //                     'driver_id' => $driverId,
    //                     'status' => 'new',
    //                     'address' => $order->user->address,
    //                 ]);
    //             }

    //             $order->update(['last_status' => 'shipped']);
    //         }

    //         if ($assignToAll) {
    //             $drivers = User::where('role', 'driver')->get();
    //             foreach ($drivers as $driver) {
    //                 $this->sendDriverNotification($summary, $driver);
    //             }
    //         } else {
    //             $this->sendDriverNotification($summary, $driver);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Orders assigned to driver successfully',
    //             'summary' => $summary,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Order assignment failed: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to assign orders: ' . $e->getMessage()], 500);
    //     }
    // }

    // private function sendDriverNotification($summary, $driver)
    // {
    //     $driver->notify(new DriverOrderAssignedNotification($summary));
    // }

    // public function acceptOrder(Request $request, $orderId)
    // {
    //     try {
    //         $driver = Auth::user();
    
    //         $order = Order::findOrFail($orderId);
    //         if ($order->last_status !== 'shipped') {
    //             return response()->json(['error' => 'Order cannot be accepted at this stage'], 403);
    //         }
    
    //         $existingDelivery = Delivery::where('order_id', $orderId)->first();
    
    //         if ($existingDelivery) {
    //             if ($existingDelivery->driver_id !== $driver->id) {
    //                 return response()->json(['error' => 'Order already assigned to another driver'], 403);
    //             }
    
    //             $existingDelivery->update([
    //                 'status' => 'in_progress',
    //             ]);
    //         } else {
    //             Delivery::create([
    //                 'order_id' => $orderId,
    //                 'driver_id' => $driver->id,
    //                 'status' => 'in_progress',
    //                 'address' => $order->user->address,
    //             ]);
    //         }
    
    //         $admin = User::where('role', 'admin')->first();
    //         if ($admin) {
    //             $admin->notify(new OrderAssignedNotification($order, $driver));
    //         }
    
    //         return response()->json(['message' => 'Order accepted successfully'], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    // public function deliverOrder($orderId)
    // {

    //     $order = Order::findOrFail($orderId);

    //     if ($order->last_status !== 'shipped') {
    //         return response()->json(['error' => 'Only shipped orders can be delivered'], 403);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $order->update(['last_status' => 'delivered']);

    //         $order->user->notify(new OrderDelivered($order));

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Order delivered successfully',
    //             'order_id' => $order->id,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Order delivery failed: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to deliver order: ' . $e->getMessage()], 500);
    //     }
    // }

   
    // public function getDriverOrders()

    // {
    //     $driverId = auth()->id();

    //     $orders = Order::where('last_status', 'shipped')
    //                    ->whereHas('delivery', function ($query) use ($driverId) {
    //                        $query->where('driver_id', $driverId);
    //                    })
    //                    ->with('orderItems.product', 'user')
    //                    ->get();

    //     $orderDetails = $orders->map(function ($order) {
    //         return [
    //             'order_id' => $order->id,
    //             'customer_name' => $order->user->first_name . ' ' . ($order->user->last_name ?? ''),
    //             'customer_address' => $order->delivery->address,
    //             'customer_phone' => $order->user->phone,
    //             'total_price' => $order->total_price,
    //             'items' => $order->orderItems->map(function ($item) {
    //                 return [
    //                     'product_name' => $item->product->product_name,
    //                     'quantity' => $item->quantity,
    //                     'price' => $item->price,
    //                 ];
    //             }),
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'orders' => $orderDetails,
    //     ], 200);
    // }
  public function show(string $id)
{
    $userId = auth()->id();

    $order = Order::where('id', $id)
                  ->where('user_id', $userId)
                  ->with('Items.product', 'delivery')
                  ->firstOrFail();

    $isAwaitingPrice = $order->last_status === 'awaiting_price_confirmation';

    $orderDetails = [
        'order_id' => $order->id,
        'date' => $order->date,
        'status' => $order->last_status,
        'shipping_address' => $order->delivery?->address ?? 'No shipping address available',
    ];

    $items = $order->Items->map(function ($item) use ($isAwaitingPrice) {
        $itemDetails = [
            'product_name' => $item->product->product_name,
            'quantity' => $item->quantity,
        ];

        if (!$isAwaitingPrice) {
            $itemDetails['price'] = $item->price;
            $itemDetails['total'] = $item->price * $item->quantity;
        }

        return $itemDetails;
    });

    $orderDetails['items'] = $items;

    if (!$isAwaitingPrice) {
        $orderDetails['total_price'] = $items->sum('total');
    }

    return response()->json([
        'status' => 'success',
        'order' => $orderDetails,
    ], 200);
}
   public function getOrdersByStatus(Request $request, $status)
    {
        try {
            $statusMap = [
                'to-receive' => ['shipped', 'processing'],
                'completed' => 'delivered',
                'cancelled' => 'canceled',
            ];
    
            if (!array_key_exists($status, $statusMap)) {
                return response()->json(['error' => 'Invalid status'], 400);
            }
    
            $mappedStatus = $statusMap[$status];
    
            $query = Order::where('user_id', Auth::id())
                          ->with(['items.product', 'delivery']);
    
            if (is_array($mappedStatus)) {
                $query->whereIn('last_status', $mappedStatus);
            } else {
                $query->where('last_status', $mappedStatus);
            }
    
            $orders = $query->get();
    
            return response()->json([
                'message' => ucfirst($status) . ' orders retrieved successfully',
                'data' => $orders->map(function ($order) {
                    $estimatedArrival = $this->calculateEstimatedArrival($order);
    
                    $totalPrice = $order->items->sum(function ($item) {
                        return $item->quantity * ($item->price ?? $item->product->price);
                    });
    
            
                    $response = [
                        'order_number' => $order->id,
                        'total_price' => $totalPrice > 0 ? number_format($totalPrice, 2) . ' SAR' : 'Not available',
                        'created_at' => $order->created_at->format('d/m/Y h:i A'),
                        'status' => $order->last_status,
                    ];
    
                   
                    if ($order->last_status !== 'processing') {
                        $response['estimated_arrival'] = $estimatedArrival;
                    }
    
                    return $response;
                }),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    private function calculateEstimatedArrival($order)
    {
        if ($order->last_status === 'canceled') {
            return 'Pick up was unsuccessful';
        }
    
        if ($order->last_status === 'delivered') {
            if ($order->delivery && $order->delivery->delivery_time) {
                return 'Delivered on ' . $order->delivery->delivery_time->format('d/m/Y h:i A');
            }
            return 'Delivered';
        }
    
        if ($order->last_status === 'shipped') {
            $baseDeliveryTime = 40;
            $createdAt = $order->created_at;
            $estimatedDeliveryTime = $createdAt->copy()->addMinutes($baseDeliveryTime);
            $now = now();
    
            if ($now->lessThan($estimatedDeliveryTime)) {
                $remainingMinutes = $now->diffInMinutes($estimatedDeliveryTime);
                return "Arriving in $remainingMinutes min";
            }
    
            return 'Delayed';
        }
    
        return 'Unknown status';
    }
}
