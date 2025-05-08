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
                        ->with(['items.product' => function ($query) {
                            $query->whereNull('deleted_at');
                        }])
                        ->first();
    
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json(['message' => 'No items in the cart'], 200);
            }
    
            $isSpotModeActive = SpotMode::isActive();
    
            $cartItems = $cart->items->map(function ($item) use ($isSpotModeActive) {
                if ($item->product) {
                    $price = $isSpotModeActive ? $item->product->price : $item->product->regular_price;
                    return [
                        'id' => $item->id,
                        'cart_id' => $item->cart_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->product_name,
                        'quantity' => $item->quantity,
                        'price' => $price,
                        'total' => $price * $item->quantity,
                        'cover_image' => $item->product->cover_image ? asset('Uploads/products/' . $item->product->cover_image) : 'No image available',
                    ];
                }
                return null; 
            })->filter();
    
            if ($cartItems->isEmpty()) {
                return response()->json(['message' => 'No valid items in the cart'], 200);
            }
    
            return response()->json([
                'message' => 'Cart items retrieved successfully',
                'data' => $cartItems->values(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $userId = Auth::id();
    
        // التأكد من وجود كارت pending
        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->first();
    
        if (!$cart) {
            return response()->json(['error' => 'No pending cart found. Please create a cart first.'], 400);
        }
    
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);
    
        $product = Product::where('id', $request->product_id)
                          ->whereNull('deleted_at')
                          ->first();
        if (!$product) {
            return response()->json(['error' => 'Product not found or has been deleted'], 404);
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
        $userId = Auth::id();
    
        // التأكد من وجود كارت pending
        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->first();
    
        if (!$cart) {
            return response()->json(['error' => 'No pending cart found'], 400);
        }
    
        $cartItem = CartItem::where('id', $id)
                            ->where('cart_id', $cart->id)
                            ->firstOrFail();
    
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
    
        $product = Product::where('id', $cartItem->product_id)
                          ->whereNull('deleted_at')
                          ->first();
        if (!$product) {
            return response()->json(['error' => 'Product not found or has been deleted'], 404);
        }
    
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
        $userId = Auth::id();
    
        // التأكد من وجود كارت pending
        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->first();
    
        if (!$cart) {
            return response()->json(['error' => 'No pending cart found'], 400);
        }
    
        $cartItem = CartItem::where('id', $id)
                            ->where('cart_id', $cart->id)
                            ->firstOrFail();
    
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
