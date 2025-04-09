<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\OrderItem;
use App\Models\SpotMode;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
    
        $cart = Cart::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'awaiting_price_confirmation'])
                    ->with('items.product')
                    ->first();
    
        if (!$cart) {
            return response()->json([
                'status' => 'success',
                'message' => 'No cart found for this user',
                'cart' => null,
            ], 200);
        }
    
        $isSpotModeActive = SpotMode::isActive();
    
        $cartData = [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'status' => $cart->status,
            'total_price' => $isSpotModeActive ? $cart->items->sum(function ($item) {
                return $item->product->price * $item->quantity;
            }) : null,
            'items' => $cart->items->map(function ($item) use ($isSpotModeActive) {
                $price = $isSpotModeActive ? $item->product->price : null;
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $price ?? 'Price to be confirmed later',
                    'total' => $price ? ($item->quantity * $price) : null,
                ];
            }),
        ];
    
        return response()->json([
            'status' => 'success',
            'cart' => $cartData,
        ], 200);
    }

    public function store()
    {
        $userId = Auth::id();
    
      
        $existingCart = Cart::where('user_id', $userId)
                            ->whereIn('status', ['pending', 'awaiting_price_confirmation'])
                            ->first();
    
        if ($existingCart) {
            return response()->json([
                'status' => 'error',
                'message' => "You already have an active cart with status '{$existingCart->status}'. Please complete or cancel it first.",
                'cart_id' => $existingCart->id,
            ], 400);
        }
    
        $cart = Cart::create([
            'user_id' => $userId,
            'status' => 'pending',
            'total_price' => 0,
        ]);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Cart created successfully',
            'cart' => [
                'id' => $cart->id,
                'user_id' => $cart->user_id,
                'status' => $cart->status,
                'total_price' => $cart->total_price,
            ],
        ], 201);
    }
    // public function update(Request $request, $id)
    // {
    //     $cart = Cart::where('id', $id)
    //                 ->where('user_id', Auth::id())
    //                 ->with('items.product')
    //                 ->firstOrFail();
    
    //     if (!in_array($cart->status, ['pending', 'awaiting_price_confirmation'])) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Cannot update cart. Only pending or awaiting price confirmation carts can be updated.',
    //         ], 403);
    //     }
    
    //     $request->validate([
    //         'status' => 'required|in:pending,confirmed,canceled,awaiting_price_confirmation',
    //     ]);
    
    //     $newStatus = $request->status;
    
    //     if ($newStatus === 'confirmed') {
    //         if ($cart->status === 'confirmed') {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Cart is already confirmed.',
    //             ], 400);
    //         }
    
    //         if ($cart->items->isEmpty()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Cannot confirm an empty cart.',
    //             ], 400);
    //         }
    
    //         DB::beginTransaction();
    //         try {
    //             $isSpotModeActive = SpotMode::isActive();
    //             $orderStatus = $isSpotModeActive ? 'pending' : 'awaiting_price_confirmation';
    
    //             $order = Order::create([
    //                 'user_id' => $cart->user_id,
    //                 'last_status' => $orderStatus,
    //                 'date' => now()->toDateString(),
    //                 'total_price' => 0,
    //             ]);
    
    //             $totalOrderPrice = 0;
    
    //             foreach ($cart->items as $cartItem) {
    //                 if ($isSpotModeActive && $cartItem->product->stock < $cartItem->quantity) {
    //                     throw new \Exception('Insufficient stock for product: ' . $cartItem->product->product_name);
    //                 }
    //                 $price = $isSpotModeActive ? $cartItem->product->price : 0;
    //                 $totalPrice = $price * $cartItem->quantity;
    
    //                 OrderItem::create([
    //                     'order_id' => $order->id,
    //                     'product_id' => $cartItem->product_id,
    //                     'quantity' => $cartItem->quantity,
    //                     'price' => $price,
    //                     'total_price' => $totalPrice,
    //                 ]);
    
    //                 if ($isSpotModeActive) {
    //                     $cartItem->product->stock -= $cartItem->quantity;
    //                     $cartItem->product->save();
    //                     $totalOrderPrice += $totalPrice;
    //                 }
    //             }
    
    //             $order->update(['total_price' => $totalOrderPrice]);
    
    //             $shippingAddress = $cart->user->addresses()->where('type', 'shipping')->first();
    //             if (!$shippingAddress) {
    //                 throw new \Exception('No shipping address found for the user.');
    //             }
    
    //             if ($isSpotModeActive) {
    //                 Delivery::create([
    //                     'order_id' => $order->id,
    //                     'user_id' => $cart->user_id,
    //                     'address' => $shippingAddress->address,
    //                     'status' => 'new',
    //                 ]);
    //             }
    
    //             $cart->update(['status' => 'confirmed']);
    //             DB::commit();
    
    //             $message = $isSpotModeActive
    //                 ? 'Cart confirmed successfully and converted to an order.'
    //                 : 'Cart confirmed with Buy Anyway option. Order awaiting price confirmation.';
    
    //             return response()->json([
    //                 'message' => $message,
    //                 'order_id' => $order->id,
    //             ], 200);
    //         } catch (\Exception $e) {
    //             DB::rollBack();
    //             return response()->json(['status' => 'error', 'message' => 'Failed to confirm cart: ' . $e->getMessage()], 500);
    //         }
    //     }
    
    //     $cart->update(['status' => $newStatus]);
    
    //     $response = [
    //         'message' => 'Cart updated successfully',
    //         'cart_id' => $cart->id,
    //         'status' => $cart->status,
    //     ];
    
    //     if ($newStatus === 'awaiting_price_confirmation') {
    //         $response['items'] = $cart->items->map(function ($item) {
    //             return [
    //                 'product_id' => $item->product_id,
    //                 'quantity' => $item->quantity,
    //                 'price' => 'Price to be confirmed later',
    //             ];
    //         });
    //     } else {
    //         $response['items'] = $cart->items->map(function ($item) {
    //             return [
    //                 'product_id' => $item->product_id,
    //                 'quantity' => $item->quantity,
    //                 'price' => $item->product->price,
    //             ];
    //         });
    //     }
    
    //     return response()->json($response, 200);
    // }

    public function cancelCart(Request $request)
    {
        $userId = auth()->id();
        $cart = Cart::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'awaiting_price_confirmation'])
                    ->firstOrFail();
    
        $cart->update(['status' => 'canceled']);
        return response()->json(['message' => 'Cart canceled successfully'], 200);
    }

    public function destroy($id)
    {
        $cart = Cart::where('id', $id)
                    ->where('user_id', Auth::id())
                    ->firstOrFail();

       
        if (!in_array($cart->status, ['pending', 'awaiting_price_confirmation'])) {
            return response()->json([
                'error' => 'Cannot delete cart. Only pending or awaiting price confirmation carts can be deleted.',
            ], 403);
        }

        $cart->delete();

        return response()->json([
            'message' => 'Cart deleted successfully',
        ], 200);
    }
}