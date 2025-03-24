<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;
use App\Http\Resources\CartItemsResource;

use Illuminate\Support\Facades\Auth;

class CartItemsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function getMyItems()
    {
        try {
            $cart = Cart::where('user_id', Auth::id())
                        ->where('status', 'pending')
                        ->with('Items.product')
                        ->first();
    
            if (!$cart || $cart->Items->isEmpty()) {
                return response()->json(['message' => 'No items found in your cart'], 200);
            }
    
            return CartItemsResource::collection($cart->Items);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    
}
    public function store(Request $request)
    {
        $userId = Auth::id();

        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $userId,
                'status' => 'pending',
                'total_price' => 0,
            ]);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json(['error' => 'Requested quantity exceeds available stock'], 400);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $request->product_id)
                            ->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
            ]);
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $product->price,
            ]);
        }

        $cart->calculateTotalPrice();

        return response()->json([
            'message' => 'Product added to cart successfully',
            'cart_item' => $cartItem,
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


     ////// test ok
    public function update(Request $request, $id)
{
    $cartItem = CartItem::where('id', $id)
                        ->whereHas('cart', function ($query) {
                            $query->where('user_id', Auth::id());
                        })
                        ->firstOrFail();

    if (in_array($cartItem->cart->status, ['confirmed', 'canceled'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot update cart item. The cart must be in a pending state.',
        ], 403);
    }

    $request->validate([
        'quantity' => 'required|integer|min:1',
    ]);

    $product = $cartItem->product;

    $quantityDifference = $request->quantity - $cartItem->quantity;
    if ($quantityDifference > 0 && $quantityDifference > $product->stock) {
        return response()->json(['error' => 'Requested quantity exceeds available stock'], 400);
    }

    $cartItem->update([
        'quantity' => $request->quantity,
    ]);

    $cartItem->cart->calculateTotalPrice();

    return response()->json([
        'message' => 'Cart item updated successfully',
        'cart_item' => $cartItem,
    ], 200);
}

    //// test ok 
public function destroy($id)
{
    $cartItem = CartItem::where('id', $id)
                        ->whereHas('cart', function ($query) {
                            $query->where('user_id', Auth::id());
                        })
                        ->firstOrFail();

    if (in_array($cartItem->cart->status, ['confirmed', 'canceled'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot delete cart item. The cart must be in a pending state.',
        ], 403);
    }

    $cartItem->delete();

    $cartItem->cart->calculateTotalPrice();

    return response()->json([
        'message' => 'Product removed from cart successfully',
    ], 200);
}

}    