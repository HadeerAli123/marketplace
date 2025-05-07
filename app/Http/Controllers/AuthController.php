<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UserResource;

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
            'fcm_token' => 'nullable|string',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = ImageService::upload($request->file('image'), 'user_photos');
        }

        $otp = 1234;

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
            'status' => 'inactive',
            'fcm_token' => $request->fcm_token,

        ]);
        
        // $user->addresses()->createMany([
        //     [
        //         'country' => 'السعودية',
        //         'state' => null,
        //         'zip_code' => null,
        //         'city' => 'الرياض',
        //         'address' => 'عنوان الفاتورة الافتراضي',
        //         'type' => 'billing',
        //         'company_name' => null,
        //     ],
        //     [
        //         'country' => 'السعودية',
        //         'state' => null,
        //         'zip_code' => null,
        //         'city' => 'الرياض',
        //         'address' => 'عنوان الشحن الافتراضي',
        //         'type' => 'shipping',
        //         'company_name' => null,
        //     ],
        // ]);
        
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
        $user->status = 'active';
        $user->save();
        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'user' => new UserResource($user),
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
            'fcm_token' => 'nullable|string',

        ]);

        if (!Auth::attempt(['phone' => $request->phone, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid phone or password'], 401);
        }
        $user = Auth::user();

        if ($user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Account is not activated. Please verify your OTP.',
            ], 403);
        }

        if ($request->filled('fcm_token')) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }

        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['message' => 'Login successful', 'token' => $token,'user' => new UserResource($user),]);
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
            'username' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'secondary_email' => 'nullable|email|unique:users,secondary_email,' . $user->id,
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['first_name', 'last_name', 'phone', 'email', 'secondary_email','username']);

        if ($request->hasFile('image')) {
            if ($user->image) {
                ImageService::delete($user->image);
            }

            $imagePath = ImageService::upload($request->file('image'), 'user_photos');
            $data['image'] = $imagePath;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user),
                ]);
    }




    public function deleteAccount(Request $request)
    {
        $user = Auth::user();

        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'Your account has been deleted successfully.',
        ]);
    }



    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

        public function sendOtpForPasswordReset(Request $request)
    {
        $request->validate([
            'phone' => 'required|exists:users,phone',
        ]);

        $otp = 1234;
        Cache::put('reset_otp_' . $request->phone, $otp, now()->addMinutes(10));

        // TODO: Send OTP via SMS


        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your phone.',
        ]);
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|exists:users,phone',
            'otp' => 'required|numeric',
        ]);

        $cachedOtp = Cache::get('reset_otp_' . $request->phone);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        Cache::put('otp_verified_' . $request->phone, true, now()->addMinutes(10));

        return response()->json([
            'status' => true,
            'message' => 'OTP verified. You can now reset your password.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone' => 'required|exists:users,phone',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Cache::get('otp_verified_' . $request->phone)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP verification required.',
            ], 403);
        }

        $user = User::where('phone', $request->phone)->first();

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'New password must not be the same as the old password.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        Cache::forget('otp_verified_' . $request->phone);
        Cache::forget('reset_otp_' . $request->phone);

        return response()->json([
            'status' => true,
            'message' => 'Password has been reset successfully.',
        ]);
    }

    public function show()
    {
        $user = Auth::user(); // This will return the authenticated user instance directly

        return response()->json([
            'status' => 'success',
            'user' => new UserResource($user),
        ], 200);
    }

}
