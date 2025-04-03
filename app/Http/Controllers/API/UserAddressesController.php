<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UsersAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'type' => 'required|in:billing,shipping',
            'company_name' => 'nullable|string',
        ]);

        $existingAddress = UsersAddress::where('user_id', $user->id)
            ->where('type', $request->type)
            ->first();

        if ($existingAddress) {
            return response()->json([
                'status' => 'error',
                'message' => "You already have a {$request->type} address. Please update the existing one or delete it first.",
            ], 400);
        }

        $address = UsersAddress::create([
            'user_id' => $user->id,
            'country' => $request->country,
            'state' => $request->state,
            'zip_code' => $request->zip_code,
            'city' => $request->city,
            'address' => $request->address,
            'type' => $request->type,
            'company_name' => $request->company_name,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Address created successfully',
            'data' => $address,
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        $address = UsersAddress::where('user_id', $user->id)->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $address,
        ], 200);
    }

    public function update(Request $request, $id)
{
    $user = Auth::user();
    $address = UsersAddress::where('user_id', $user->id)->findOrFail($id);

    $request->validate([
        'country' => 'required|string',
        'state' => 'nullable|string',
        'zip_code' => 'nullable|string',
        'city' => 'required|string',
        'address' => 'required|string',
        'type' => 'required|in:billing,shipping',
        'company_name' => 'nullable|string',
    ]);

    if ($address->type !== $request->type) {
        $existingAddress = UsersAddress::where('user_id', $user->id)
            ->where('type', $request->type)
            ->where('id', '!=', $id)
            ->first();

       
        if ($existingAddress) {
            $existingAddress->delete();
        }
    }

    
    $address->update([
        'country' => $request->country,
        'state' => $request->state,
        'zip_code' => $request->zip_code,
        'city' => $request->city,
        'address' => $request->address,
        'type' => $request->type,
        'company_name' => $request->company_name,
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Address updated successfully',
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
            'message' => 'Address deleted successfully',
        ], 200);
    }
}
