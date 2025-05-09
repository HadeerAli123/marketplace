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
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    
    ///////////////////////////////////////////////testok
    
    public function index()
    {
        $userId = auth()->id();

        $orders = Order::where('user_id', $userId)
                       ->select('id', 'date', 'last_status')
                       ->with(['items' => function ($query) {
                           $query->select('id', 'order_id', 'product_id', 'quantity', 'price')
                                 ->with(['product' => function ($q) {
                                     $q->select('id', 'product_name');
                                 }]);
                       }])
                       ->orderBy('date', 'desc') // الترتيب الأساسي بناءً على date
                       ->orderBy('created_at', 'desc') // ترتيب ثانوي بناءً على created_at
                       ->orderBy('id', 'desc') // ترتيب ثالث بناءً على id
                       ->get();

        $orderDetails = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'date' => $order->date,
                'status' => $order->last_status,
                'products' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product ? $item->product->product_name : 'Product not found',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
                'total_price' => $order->items->sum(function ($item) {
                    return $item->price * $item->quantity;
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'orders' => $orderDetails,
        ], 200);
    }
/////////////////////////////////////////////////////test ok
public function createOrder(Request $request)
{
    $userId = auth()->id();

    $cart = Cart::where('user_id', $userId)
                ->where('status', 'pending')
                ->with(['items' => function ($query) {
                    $query->select('id', 'cart_id', 'product_id', 'quantity', 'price')
                          ->with(['product' => function ($q) {
                              $q->select('id', 'product_name', 'price', 'regular_price', 'stock');
                          }]);
                }])
                ->first();

    if (!$cart) {
        return response()->json(['error' => 'No cart found'], 400);
    }


    if ($cart->items->isEmpty()) {
        return response()->json(['error' => 'Cart is empty'], 400);
    }

    $shippingAddress = UsersAddress::where('user_id', $userId)
                                   ->where('type', 'shipping')
                                   ->first();

    if (!$shippingAddress) {
        return response()->json(['error' => 'No shipping address found. Please add a shipping address first.'], 400);
    }

    $request->validate([
        'notes' => 'nullable|string',
    ]);

    $currentTime = now();
    $orderDate = $currentTime->hour >= 6 ? $currentTime->toDateString() : $currentTime->subDay()->toDateString();

    $isSpotModeActive = SpotMode::isActive();
    $notes = $request->input('notes', null);

    DB::beginTransaction();

    try {
        $order = Order::create([
            'user_id' => $userId,
            'last_status' => 'processing',
            'date' => $orderDate,
            'notes' => $notes,
        ]);

        $totalOrderPrice = 0;

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;

        
            if (!$product) {
                throw new \Exception('Product with ID ' . $cartItem->product_id . ' not found');
            }

            if ($product->stock <= 0) {
                throw new \Exception('Product ' . $product->product_name . ' is out of stock');
            }

            if ($cartItem->quantity > $product->stock) {
                throw new \Exception('Requested quantity for ' . $product->product_name . ' exceeds available stock');
            }

            $price = $isSpotModeActive ? $product->price : $product->regular_price;
            if ($price === null) {
                throw new \Exception('Price not available for product: ' . $product->product_name);
            }

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

        $cart->update(['status' => 'confirmed']);

        DB::commit();

        return response()->json([
            'message' => 'Order created successfully',
            'order_id' => $order->id,
            'status' => $order->last_status,
            'total_price' => $totalOrderPrice,
            'notes' => $order->notes,
            'address' => $shippingAddress->address,
            'products' => $order->items->map(function ($item) {
                return [
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];
            }),
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Order creation failed', [
            'message' => $e->getMessage(),
            'user_id' => $userId,
            'cart_id' => $cart->id ?? null,
            'stack' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Order creation failed: ' . $e->getMessage()], 500);
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
                      ->with(['items' => function ($query) {
                          $query->select('id', 'order_id', 'product_id', 'quantity', 'price')
                                ->with(['product' => function ($q) {
                                    $q->select('id', 'product_name');
                                }]);
                      }, 'delivery' => function ($query) {
                          $query->select('id', 'order_id', 'driver_id', 'delivery_fee')
                                ->with(['driver' => function ($q) {
                                    $q->select('id', 'first_name', 'last_name', 'phone');
                                }]);
                      }])
                      ->first();
    
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
    
        $shippingAddress = UsersAddress::where('user_id', $userId)
                                       ->where('type', 'shipping')
                                       ->first();
    
        if (!$shippingAddress) {
            return response()->json(['error' => 'No shipping address found for this user'], 400);
        }
    
        $orderDetails = [
            'order_id' => $order->id,
            'date' => $order->date,
            'status' => $order->last_status,
            'shipping_address' => $shippingAddress->address,
            'delivery_man' => $order->delivery && $order->delivery->driver 
                ? ($order->delivery->driver->first_name . ' ' . $order->delivery->driver->last_name) 
                : 'Not assigned',
            'delivery_man_phone' => $order->delivery && $order->delivery->driver 
                ? $order->delivery->driver->phone 
                : 'Not available',
            'delivery_fee' => $order->delivery ? $order->delivery->delivery_fee : 'Not available',
            'items' => $order->items->map(function ($item) {
                return [
                    'product_name' => $item->product ? $item->product->product_name : 'Product not found',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->price * $item->quantity,
                ];
            }),
            'total_price' => $order->items->sum(function ($item) {
                return $item->price * $item->quantity;
            }),
        ];
    
        return response()->json([
            'status' => 'success',
            'message' => 'Order details retrieved successfully',
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
                          ->with(['items' => function ($query) {
                              $query->select('id', 'order_id', 'product_id', 'quantity', 'price')
                                    ->with(['product' => function ($q) {
                                        $q->select('id', 'product_name');
                                    }]);
                          }, 'delivery' => function ($query) {
                              $query->select('id', 'order_id', 'delivery_time');
                          }]);

            if (is_array($mappedStatus)) {
                $query->whereIn('last_status', $mappedStatus);
            } else {
                $query->where('last_status', $mappedStatus);
            }

            $orders = $query->orderBy('created_at', 'desc') 
                            ->orderBy('id', 'desc')
                            ->get();

            return response()->json([
                'message' => ucfirst($status) . ' orders retrieved successfully',
                'data' => $orders->map(function ($order) {
                    $estimatedArrival = $this->calculateEstimatedArrival($order);
                    $totalPrice = $order->items->sum(function ($item) {
                        return $item->quantity * $item->price;
                    });
                    $response = [
                        'order_number' => $order->id,
                        'total_price' => $totalPrice > 0 ? number_format($totalPrice, 2) : 'Not available',
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
            \Log::error('Get orders by status failed', [
                'status' => $status,
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function calculateEstimatedArrival($order)
    {
        if ($order->last_status === 'canceled') {
            return 'Order canceled';
        }

        if ($order->last_status === 'delivered') {
            if ($order->delivery && $order->delivery->delivery_time) {
                return $order->delivery->delivery_time->format('d/m/Y h:i A');
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
                
                return " " . intval($remainingMinutes) ;
            }

            return $estimatedDeliveryTime->format('d/m/Y h:i A');
        }

        return 'Unknown status';
    }
}