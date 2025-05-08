<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FCMNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Send to all users
    public function sendToAllUsers(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'body'  => 'required|string',
        ]);

        $users = User::whereNotNull('fcm_token')->get();

        foreach ($users as $user) {
            $this->sendFCM($user->fcm_token, $request->title, $request->body);

            Notification::create([
                'title'   => $request->title,
                'body'    => $request->body,
                'user_id' => $user->id,
            ]);
        }

        return response()->json(['message' => 'Notification sent to all users']);
    }

    // Send to specific customer
    public function sendToCustomer(Request $request)
    {
        $request->validate([
            'title'   => 'required|string',
            'body'    => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::where('id', $request->user_id)->where('role', 'customer')->first();

        if (!$user || !$user->fcm_token) {
            return response()->json(['message' => 'Customer not found or no FCM token'], 404);
        }

        $this->sendFCM($user->fcm_token, $request->title, $request->body);

        Notification::create([
            'title'   => $request->title,
            'body'    => $request->body,
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Notification sent to customer']);
    }

    // Send to specific driver
    public function sendToDriver(Request $request)
    {
        $request->validate([
            'title'   => 'required|string',
            'body'    => 'required|string',
            'driver_id' => 'required|exists:users,id',
        ]);

        $driver = User::where('id', $request->driver_id)->where('role', 'driver')->first();

        if (!$driver || !$driver->fcm_token) {
            return response()->json(['message' => 'Driver not found or no FCM token'], 404);
        }

        $this->sendFCM($driver->fcm_token, $request->title, $request->body);

        Notification::create([
            'title'   => $request->title,
            'body'    => $request->body,
            'user_id' => $driver->id,
        ]);

        return response()->json(['message' => 'Notification sent to driver']);
    }

    // Helper method to send via FCM
    private function sendFCM($token, $title, $body)
    {
        $messaging = Firebase::messaging();

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FCMNotification::create($title, $body));

        $messaging->send($message);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);
    
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
    
        $user->fcm_token = $request->fcm_token;
        $user->device_id = $request->device_id;
        $user->save();
    
        return response()->json(['message' => 'FCM token and device ID updated successfully.']);
    }
    
    public function index(Request $request)
    {
        $user = Auth::user();
    
        $notifications = $user->notifications()
            ->latest()
            ->get();
    
        return response()->json([
            'notifications' => $notifications
        ]);
    }

    public function markAsRead($notificationId)
    {
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $notificationId)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->notifications()->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

}
