<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\SpotMode;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CartItemsController extends Controller
{
    public function getMyItems()
    {
        try {
            $cart = Cart::where('user_id', Auth::id())
                        ->where('status', 'pending')
                        ->with('items.product')
                        ->first();
    
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json(['message' => 'No items in the cart'], 200);
            }
    
            $isSpotModeActive = SpotMode::isActive();
    
            $cartItems = $cart->items->map(function ($item) use ($isSpotModeActive) {
                $price = $isSpotModeActive ? $item->product->price : $item->product->regular_price;
                return [
                    'id' => $item->id,
                    'cart_id' => $item->cart_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $price,
                    'total' => $price * $item->quantity,
                    'cover_image' => $item->product->cover_image ? asset('uploads/products/' . $item->product->cover_image) : 'No image available',
                ];
            });
    
            return response()->json([
                'message' => 'Cart items retrieved successfully',
                'data' => $cartItems,
            ], 200);
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
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);
    

        $product = Product::find($request->product_id);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    
    
        if ($product->stock < $request->quantity) {
            return response()->json(['error' => 'The requested quantity exceeds the available stock'], 400);
        }
    
        $isSpotModeActive = SpotMode::isActive();
        $price = $isSpotModeActive ? $product->price : $product->regular_price;
    
        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $request->product_id)
                            ->first();
    
        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($product->stock < $newQuantity) {
                return response()->json(['error' => 'The requested quantity exceeds the available stock'], 400);
            }
    
            $cartItem->update([
                'quantity' => $newQuantity,
                'price' => $price,
            ]);
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $price,
            ]);
        }
    
        $cart->total_price = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $cart->save();
    
        return response()->json([
            'message' => 'Product added to cart successfully',
            'cart_item' => [
                'id' => $cartItem->id,
                'cart_id' => $cartItem->cart_id,
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'created_at' => $cartItem->created_at,
                'updated_at' => $cartItem->updated_at,
            ],
        ], 201);
    }
    
    
    public function update(Request $request, $id)
    {
        $cartItem = CartItem::where('id', $id)
                            ->whereHas('cart', function ($query) {
                                $query->where('user_id', Auth::id());
                            })
                            ->firstOrFail();
    
        if ($cartItem->cart->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update cart item. The cart must be in pending status.',
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
    
        $isSpotModeActive = SpotMode::isActive();
        $price = $isSpotModeActive ? $product->price : $product->regular_price;
    
        $cartItem->update([
            'quantity' => $request->quantity,
            'price' => $price,
        ]);
    
        $cart = $cartItem->cart;
        $cart->total_price = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $cart->save();
    
        return response()->json([
            'message' => 'Cart item updated successfully',
            'cart_item' => [
                'id' => $cartItem->id,
                'cart_id' => $cartItem->cart_id,
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'created_at' => $cartItem->created_at,
                'updated_at' => $cartItem->updated_at,
            ],
        ], 200);
    }

    public function destroy($id)
    {
        $cartItem = CartItem::where('id', $id)
                            ->whereHas('cart', function ($query) {
                                $query->where('user_id', Auth::id());
                            })
                            ->firstOrFail();

        if ($cartItem->cart->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete cart item. The cart must be in pending status.',
            ], 403);
        }

        $cart = $cartItem->cart;
        $cartItem->delete();

        $cart->total_price = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $cart->save();

        return response()->json([
            'message' => 'Product removed from cart successfully',
        ], 200);
    }
}
