<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Delivery;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function getAvailableOrdersForDelivery(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driverLat = $request->lat;
        $driverLng = $request->lng;

        $orders = Order::where('last_status', 'processing')
            ->whereDoesntHave('delivery')
            ->with('user.shippingAddress') 
            ->get();

        $ordersWithDistance = $orders->map(function ($order) use ($driverLat, $driverLng) {
            $customerLat = $order->user->shippingAddress->lat ?? null;
            $customerLng = $order->user->shippingAddress->lng ?? null;
            $distance = $this->calculateDistance($driverLat, $driverLng, $customerLat, $customerLng);

            $orderResource = new OrderResource($order);
            return array_merge($orderResource->toArray(request()), ['distance_km' => $distance]);
        });

        return response()->json([
            'status' => true,
            'orders' => $ordersWithDistance,
        ]);
    }

    public function getMyDeliveries(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driverLat = $request->lat;
        $driverLng = $request->lng;

        $deliveries = Delivery::where('driver_id', auth()->id())
            ->whereHas('order', function ($query) {
                $query->whereIn('last_status', ['processing', 'shipped']);
            })
            ->with('order.user.shippingAddress')
            ->get();

        $orders = $deliveries->map(function ($delivery) use ($driverLat, $driverLng) {
            $order = $delivery->order;
            $customerLat = $order->user->shippingAddress->lat ?? null;
            $customerLng = $order->user->shippingAddress->lng ?? null;
            $distance = $this->calculateDistance($driverLat, $driverLng, $customerLat, $customerLng);
            $orderResource = new OrderResource($order);
            return array_merge($orderResource->toArray(request()), ['distance_km' => $distance]);
        });

        return response()->json([
            'status' => true,
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, $id)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driverLat = $request->lat;
        $driverLng = $request->lng;

        $order = Order::with(['delivery', 'products', 'user.shippingAddress'])->findOrFail($id);

        $customerLat = $order->user->shippingAddress->lat ?? null;
        $customerLng = $order->user->shippingAddress->lng ?? null;
        $distance = $this->calculateDistance($driverLat, $driverLng, $customerLat, $customerLng);

        $orderResource = new OrderResource($order);
        $data = array_merge($orderResource->toArray(request()), ['distance_km' => $distance]);

        return response()->json([
            'status' => true,
            'order' => $data,
        ]);
    }

    public function acceptOrder(Order $order)
    {
        if ($order->delivery) {
            return response()->json([
                'status' => false,
                'message' => 'Order is already assigned to a delivery driver.',
            ], 400);
        }

        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => auth()->id(),
            'delivery_time'=> Carbon::now()->addHours(1),
            'address' => $order->user->addresses ?? null,
        ]);

        $order->update([
            'last_status' => 'shipped',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order accepted successfully.',
        ]);
    }

    public function cancelAcceptance($orderId)
    {
        $delivery = Delivery::where('order_id', $orderId)
            ->where('driver_id', auth()->id())
            ->first();

        if (!$delivery) {
            return response()->json(['status' => false, 'message' => 'Delivery not found or not assigned to you'], 404);
        }

        $delivery->delete();

        return response()->json(['status' => true, 'message' => 'Order acceptance has been cancelled']);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat2) || is_null($lon2)) return null;

        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }
}
