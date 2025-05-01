<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\SpotMode;

class CartController extends Controller
{
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
                'message' => 'No cart found for this user - لا توجد سّلة لهذا المستخدم',
                'cart' => null,
            ], 200);
        }
    
        $isSpotModeActive = SpotMode::isActive();
    
        $cartData = [
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'status' => $cart->status,
            'total_price' => $cart->items->sum(function ($item) use ($isSpotModeActive) {
                $price = $isSpotModeActive ? $item->product->price : $item->product->regular_price;
                return $price * $item->quantity;
            }),
            'items' => $cart->items->map(function ($item) use ($isSpotModeActive) {
                $price = $isSpotModeActive ? $item->product->price : $item->product->regular_price;
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $price,
                    'total' => $price * $item->quantity,
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
                            ->where('status', 'pending')
                            ->first();
    
        if ($existingCart) {
            return response()->json([
                'status' => 'error',
                'message' => "You already have an open cart - لديك سّلة مفتوحة بالفعل. أكملها أو ألغِها أولاً.",
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
            'message' => 'Cart created successfully - تم إنشاء السّلة بنجاح',
            'cart' => [
                'id' => $cart->id,
                'user_id' => $cart->user_id,
                'status' => $cart->status,
                'total_price' => $cart->total_price,
            ],
        ], 201);
    }

    public function cancelCart(Request $request)
    {
        $userId = auth()->id();
        $cart = Cart::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->firstOrFail();
    
        $cart->update(['status' => 'canceled']);
        return response()->json([
            'message' => 'Cart canceled successfully - تم إلغاء السّلة بنجاح',
        ], 200);
    }

    public function destroy($id)
    {
        $cart = Cart::where('id', $id)
                    ->where('user_id', Auth::id())
                    ->where('status', 'pending')
                    ->firstOrFail();

        $cart->delete();

        return response()->json([
            'message' => 'Cart deleted successfully - تم حذف السّلة بنجاح',
        ], 200);
    }
}
