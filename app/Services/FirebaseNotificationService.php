<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FirebaseNotificationService
{
    protected $serverKey;

    public function __construct()
    {
        $credentialsPath = config('firebase.credentials');

        if (!file_exists(base_path($credentialsPath))) {
            throw new \Exception("Firebase credentials file not found at: " . base_path($credentialsPath));
        }

        $factory = (new Factory)->withServiceAccount(base_path($credentialsPath));
        $this->messaging = $factory->createMessaging();
        $this->firestore = new FirestoreClient([
            'keyFilePath' => base_path($credentialsPath),
        ]);
    }

    public function sendNotification($token, $title, $body)
    {
        $serverKey = config('services.firebase.server_key');

        $payload = [
            'to' => $token,
            'data' => [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'title' => $title,
                'body' => $body,
                'status' => 'done',
            ],
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
           
        ];
        

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);
        return $response->json();

    }
}
