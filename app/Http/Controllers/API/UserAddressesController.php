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

        $addresses->each(function ($address) {
            $address->makeHidden(['country', 'state', 'company_name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'zip_code' => 'nullable|string',
            'city' => ['required', 'string', function ($attribute, $value, $fail) {
                $validCities = array_merge(
                    array_column(config('cities'), 'name_ar'),
                    array_column(config('cities'), 'name_en')
                );
                if (!in_array($value, $validCities)) {
                    $fail('The selected city is invalid.');
                }
            }],
            'address' => 'required|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'type' => 'required|in:billing,shipping',
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
            'zip_code' => $request->zip_code,
            'city' => $request->city,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'type' => $request->type,
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

            $address = UsersAddress::where('user_id', $user->id)
                                   ->where('id', $id)
                                   ->firstOrFail();

            $address->makeHidden(['country', 'state', 'company_name']);

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
        $address = UsersAddress::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        $request->validate([
            'zip_code' => 'nullable|string',
            'city' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value) {
                    $validCities = array_merge(
                        array_column(config('cities'), 'name_ar'),
                        array_column(config('cities'), 'name_en')
                    );
                    if (!in_array($value, $validCities)) {
                        $fail('The selected city is invalid.');
                    }
                }
            }],
            'address' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'type' => 'nullable|in:billing,shipping',
        ]);

        $data = $request->only(['zip_code', 'city', 'address', 'lat', 'lng', 'type']);

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
        $address->makeHidden(['country', 'state', 'company_name']);

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

        $billingAddress->makeHidden(['country', 'state', 'company_name']);

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

     
        $shippingAddress->makeHidden(['country', 'state', 'company_name']);

        return response()->json([
            'status' => 'success',
            'data' => $shippingAddress,
        ], 200);
    }

    public function getCities()
    {
        $cities = config('cities');

        return response()->json([
            'status' => 'success',
            'data' => $cities,
        ], 200);
    }
}