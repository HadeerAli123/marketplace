<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    ///////////test ok
    public function index()
{
    $userId = Auth::id();

    $cart = Cart::where('user_id', $userId)
                ->where('status', 'pending')
                ->with('items.product')
                ->first();

    if (!$cart) {
        return response()->json([
            'status' => 'success',
            'message' => 'No cart found for this user',
            'cart' => null,
        ], 200);
    }

    $cart->calculateTotalPrice();

    if ($cart->items->isEmpty()) {
        return response()->json([
            'status' => 'success',
            'message' => 'Cart is empty',
            'cart' => [
                'id' => $cart->id,
                'user_id' => $cart->user_id,
                'status' => $cart->status,
                'total_price' => $cart->total_price,
                'items' => [],
            ],
        ], 200);
    }

    return response()->json([
        'status' => 'success',
        'cart' => [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'status' => $cart->status,
            'total_price' => $cart->total_price,
            'items' => $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->quantity * $item->price,
                ];
            }),
        ],
    ], 200);
}


    /**
     * Store a newly created resource in storage.
     */

     ///////test ok
    public function store()
    {
        $userId = Auth::id();

        $existingCart = Cart::where('user_id', $userId)
                            ->where('status', 'pending')
                            ->first();

        if ($existingCart) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have a pending cart',
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
                'items' => [],
            ],
        ], 201);
    
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
    ///////test ok ok
    public function update(Request $request, $id)
{
    $cart = Cart::where('id', $id)
                ->where('user_id', Auth::id())
                ->with('items.product')
                ->firstOrFail();

    if ($cart->status !== 'pending') {
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot update cart. Only pending carts can be updated.',
        ], 403);
    }

    $request->validate([
        'status' => 'required|in:pending,confirmed,canceled',
    ]);

    $newStatus = $request->status;

    if ($newStatus === 'confirmed') {
        if ($cart->items->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot confirm an empty cart.',
            ], 400);
        }

        DB::beginTransaction();

        try {
        
            $order = Order::create([
                'user_id' => $cart->user_id,
                'last_status' => 'pending',
                'date' => now()->toDateString(),
                'total_price' => $cart->total_price,
            ]);

            foreach ($cart->items as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total_price' => $cartItem->quantity * $cartItem->price,
                ]);
            }

      
            $shippingAddress = $cart->user->addresses()->where('type', 'shipping')->first();
            if (!$shippingAddress) {
                throw new \Exception('No shipping address found for the user.');
            }

            Delivery::create([
                'order_id' => $order->id,
                'user_id' => $cart->user_id,
                'address' => $shippingAddress->address,
                'status' => 'new',
            ]);

           
            $cart->update([
                'status' => $newStatus,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cart confirmed successfully and converted to an order.',
                'order_id' => $order->id,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Cart confirmation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm cart: ' . $e->getMessage(),
            ], 500);
        }
    }

    if ($newStatus === 'canceled') {
       
        $cart->update([
            'status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Cart canceled successfully.',
        ], 200);
    }

    
    $cart->update([
        'status' => $newStatus,
    ]);

    return response()->json([
        'message' => 'Cart updated successfully',
        'cart' => $cart,
    ], 200);
}


////// test ok
    public function destroy($id)
    {
        $cart = Cart::where('id', $id)
                    ->where('user_id', Auth::id())
                    ->firstOrFail();/// بترجع نوت فوند تلقائي
    
        if ($cart->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete cart. Only pending carts can be deleted.',
            ], 403);
        }
   
        $cart->items()->delete();
        $cart->delete();
    
        return response()->json([
            'message' => 'Cart deleted successfully',
        ], 200);
    }
    
}