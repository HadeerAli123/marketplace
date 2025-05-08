<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UsersAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserAddressesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $addresses = UsersAddress::where('user_id', $user->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'country' => 'required|string',
            'state' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'city' => 'required|string',
            'address' => 'required|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'type' => 'required|in:billing,shipping',
            'company_name' => 'nullable|string',
        ]);

        $existingAddress = UsersAddress::where('user_id', $user->id)
            ->where('type', $request->type)
            ->first();

        if ($existingAddress) {
            return response()->json([
                'status' => 'error',
                'message' => "You already have a {$request->type} address. Please update or delete it first.",
            ], 400);
        }

        $address = UsersAddress::create([
            'user_id' => $user->id,
            'country' => $request->country,
            'state' => $request->state,
            'zip_code' => $request->zip_code,
            'city' => $request->city,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'type' => $request->type,
            'company_name' => $request->company_name,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Address created successfully.',
            'data' => $address,
        ], 201);
    }


public function show($id)
{
    try {
        $user = Auth::user();

        $address = UsersAddress::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $address,
        ], 200);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Address not found',
        ], 404);
    }
}


    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $address = UsersAddress::where('user_id', $user->id)->findOrFail($id);

        $request->validate([
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'type' => 'nullable|in:billing,shipping',
            'company_name' => 'nullable|string',
        ]);

        $data = $request->only(['country', 'state', 'zip_code', 'city', 'address', 'lat', 'lng', 'type', 'company_name']);

        if ($request->has('type') && $address->type !== $request->type) {
            $existingAddress = UsersAddress::where('user_id', $user->id)
                ->where('type', $request->type)
                ->where('id', '!=', $id)
                ->first();

            if ($existingAddress) {
                $existingAddress->delete();
            }
        }

        $address->update(array_filter($data));

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated successfully.',
            'data' => $address,
        ], 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $address = UsersAddress::where('user_id', $user->id)->findOrFail($id);
        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted successfully.',
        ], 200);
    }

    public function getBillingAddress()
    {
        $user = Auth::user();
        $billingAddress = UsersAddress::where('user_id', $user->id)
                                      ->where('type', 'billing')
                                      ->first();

        if (!$billingAddress) {
            return response()->json([
                'status' => 'error',
                'message' => 'Billing address not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $billingAddress,
        ], 200);
    }

    public function getShippingAddress()
    {
        $user = Auth::user();
        $shippingAddress = UsersAddress::where('user_id', $user->id)
                                       ->where('type', 'shipping')
                                       ->first();

        if (!$shippingAddress) {
            return response()->json([
                'status' => 'error',
                'message' => 'Shipping address not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $shippingAddress,
        ], 200);
    }
}
