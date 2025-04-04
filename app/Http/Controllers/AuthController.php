<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Validator;

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
            'username' => 'nullable|string|unique:users,username',
            'role' => 'nullable|in:customer,admin,driver',
            'image' => 'nullable|image',
            'email' => 'nullable|email|unique:users,email',
            'secondary_email' => 'nullable|email|unique:users,secondary_email',
        ]);

        // Upload image if exists
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageService::upload($request->file('image'), 'user_photos');
        }

        // Generate OTP
        $otp = 1234;

        // Create the user but don't authenticate yet
        $user = User::create([
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'role' => $request->role,
            'email' => $request->email,
            'secondary_email' => $request->secondary_email,
            'image' => $imagePath,
        ]);

        // Store OTP in cache
        Cache::put('otp_' . $user->phone, $otp, now()->addMinutes(10));

        // // Send OTP via email
        // if ($user->email) {
        //     Mail::to($user->email)->send(new OtpMail($otp));
        // }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully. Please verify to activate your account.',
            'data' => null,
        ], 201);
    }


    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
            'phone' => 'required|exists:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $storedOtp = Cache::get('otp_' . $request->phone);

        if (!$storedOtp) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired or not found.',
            ], 422);
        }

        if ($storedOtp != $request->otp) {
            $user = User::where('phone', $request->phone)->first();
            if ($user) {
                $user->delete();
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP. Account has been deleted.',
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
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
