<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SpotMode;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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
            return response()->json(['error' => 'Spot Mode is already active'], 400);
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
                $carts = Cart::where('status', 'pending')
                    ->with(['items.product' => function ($query) {
                        $query->whereNull('deleted_at'); // جيب بس المنتجات اللي مش محذوفة
                    }])
                    ->get();
                foreach ($carts as $cart) {
                    foreach ($cart->items as $item) {
                        if ($item->product && !$item->product->trashed()) { // لو المنتج موجود ومش محذوف
                            $item->update(['price' => $item->product->price]);
                        } else {
                            \Log::warning("العنصر {$item->id} في الكارت مالهوش منتج أو محذوف.");
                            $item->delete(); // امسح العنصر
                        }
                    }
                    $cart->total_price = $cart->items->sum(function ($item) {
                        return $item->price * $item->quantity;
                    });
                    $cart->save();
                }
            }

            DB::commit();
            return response()->json([
                'message' => $status === 'active' 
                    ? 'Spot Mode activated successfully and cart prices updated' 
                    : 'Spot Mode scheduled successfully',
                'spot_mode' => $spotMode,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to activate Spot Mode: ' . $e->getMessage());
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

            $carts = Cart::where('status', 'pending')
                ->with(['items.product' => function ($query) {
                    $query->whereNull('deleted_at'); // جيب بس المنتجات اللي مش محذوفة
                }])
                ->get();
            foreach ($carts as $cart) {
                foreach ($cart->items as $item) {
                    if ($item->product && !$item->product->trashed()) { // لو المنتج موجود ومش محذوف
                        $item->update(['price' => $item->product->regular_price]);
                    } else {
                        \Log::warning("العنصر {$item->id} في الكارت مالهوش منتج أو محذوف.");
                        $item->delete();
                    }
                }
                $cart->total_price = $cart->items->sum(function ($item) {
                    return $item->price * $item->quantity;
                });
                $cart->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Spot Mode deactivated successfully and cart prices updated',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to deactivate Spot Mode: ' . $e->getMessage());
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
