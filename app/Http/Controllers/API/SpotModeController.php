<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SpotMode;
use App\Models\Cart;
use App\Models\Order;
use App\Models\UsersAddress;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpotModeController extends Controller
{
    public function activate(Request $request)
    {
        $request->validate([
            'activate_time' => 'required|date',
            'closing_time' => 'required|date|after:activate_time',
        ]);

        if (SpotMode::isActive()) {
            return response()->json(['error' => 'A Spot Mode is already active'], 400);
        }

        $currentTime = now();
        $activateTime = \Carbon\Carbon::parse($request->activate_time);

        DB::beginTransaction();
        try {
            $status = $currentTime->greaterThanOrEqualTo($activateTime) ? 'active' : 'pending';

            $spotMode = SpotMode::create([
                'user_id' => auth()->id(),
                'status' => $status,
                'activate_time' => $request->activate_time,
                'closing_time' => $request->closing_time,
                'created_at' => now(),
            ]);

            if ($status === 'active') {
               
                $orders = Order::where('last_status', 'awaiting_price_confirmation')
                               ->with('items.product')
                               ->get();

                foreach ($orders as $order) {
                    $shippingAddress = UsersAddress::where('user_id', $order->user_id)
                                                   ->where('type', 'shipping')
                                                   ->first();

                    if (!$shippingAddress) {
                        \Log::warning("No shipping address for order ID: {$order->id}");
                        continue;
                    }

                    $totalOrderPrice = 0;

                    foreach ($order->items as $orderItem) {
                        $product = $orderItem->product;

                        if ($product->stock < $orderItem->quantity) {
                            throw new \Exception("Insufficient stock for product: {$product->product_name}");
                        }

                        $price = $product->price; 
                        $totalPrice = $price * $orderItem->quantity;

                        $orderItem->update([
                            'price' => $price,
                            'total_price' => $totalPrice,
                        ]);

                        $totalOrderPrice += $totalPrice;
                    }

                    $order->update([
                        'last_status' => 'processing',
                        'total_price' => $totalOrderPrice,
                    ]);

                    Delivery::create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'address' => $shippingAddress->address,
                        'status' => 'new',
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'message' => $status === 'active' 
                    ? 'Spot Mode activated successfully, awaiting orders updated to pending' 
                    : 'Spot Mode scheduled successfully',
                'spot_mode' => $spotMode,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Spot Mode activation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to activate Spot Mode: ' . $e->getMessage()], 500);
        }
    }
    public function deactivate()
    {
        $spotMode = SpotMode::where('status', 'active')->first();
        if (!$spotMode) {
            return response()->json(['error' => 'No active Spot Mode found'], 404);
        }
    
        DB::beginTransaction();
        try {
            $spotMode->update(['status' => 'not_active']);
       
            $cartIds = Cart::whereIn('status', ['pending', 'awaiting_price_confirmation'])->pluck('id');
    
         
            DB::table('cart_items')
                ->whereIn('cart_id', $cartIds)
                ->delete();
    
            Cart::whereIn('id', $cartIds)->update(['status' => 'pending']);
    
            DB::commit();
            return response()->json([
                'message' => 'Spot Mode deactivated successfully, pending and awaiting carts cleared and reset to pending, ',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Spot Mode deactivation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to deactivate Spot Mode: ' . $e->getMessage()], 500);
        }
    
    }

    public function getStatus()
    {
        $isActive = SpotMode::isActive();
        $spotMode = $isActive ? SpotMode::where('status', 'active')->first() : null;

        return response()->json([
            'is_active' => $isActive,
            'spot_mode' => $spotMode ? [
                'activate_time' => $spotMode->activate_time,
                'closing_time' => $spotMode->closing_time,
            ] : null,
        ], 200);
    }
}