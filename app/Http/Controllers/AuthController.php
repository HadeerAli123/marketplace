<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
   /**
     * Register a new user
     */
    public function register(Request $request)
{
    $request->validate([
        'phone' => 'required|string|unique:users',
        'password' => 'required|string|min:6|confirmed',
        'terms' => 'accepted',
        'first_name' => 'nullable|string',
        'last_name' => 'nullable|string',
        'user_name' => 'nullable|string',
        'role' => 'nullable|in:customer,admin,driver',
        'image' => 'nullable|image',
        'email' => 'nullable|email|unique:users,email',
        'secondary_email' => 'nullable|email|unique:users,secondary_email',
    ]);

    // إنشاء المستخدم
    $user = User::create([
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'user_name' => $request->user_name,
        'role' => $request->role,
        'email' => $request->email,
        'secondary_email' => $request->secondary_email,
    ]);

    // إنشاء التوكن
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Account created successfully',
        'user' => $user,
        'token' => $token
    ], 201);
}


    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['phone' => $request->phone, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid phone or password'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['message' => 'Login successful', 'token' => $token, 'user' => $user]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Edit user profile
     */
    public function editProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'primary_email' => 'nullable|email|unique:users,primary_email,' . $user->id,
            'secondary_email' => 'nullable|email|unique:users,secondary_email,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        $user->update($request->only(['first_name', 'last_name', 'phone', 'primary_email', 'secondary_email']));

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}