<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\Client;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Pagination\LengthAwarePaginator;
class FirebaseService
{
    protected $messaging;
    protected $firestore;

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

    public function getAllChats()
    {
        $messagesCollection = $this->firestore->collectionGroup('messages')->documents();
        $chatSummaries = [];

        foreach ($messagesCollection as $messageDoc) {
            if (!$messageDoc->exists()) {
                continue;
            }

            $messageData = $messageDoc->data();
            $parentRef = $messageDoc->reference()->parent()->parent();
            if (!$parentRef) {
                continue;
            }

            $chatId = $parentRef->id();

            if (!isset($chatSummaries[$chatId])) {
                $client = Client::find($chatId);

                $chatSummaries[$chatId] = [
                    'chatId' => $chatId,
                    'clientName' => $client->name ?? 'Unknown',
                    'clientImage' => asset($client->photo) ?? null,
                    'unreadMessages' => 0,
                    'lastMessage' => null,
                    'lastMessageType' => null,
                    'lastMessageTime' => null,
                ];
            }

            $messageType = 'text';
            $lastMessageContent = $messageData['message'] ?? null;

            if (!empty($messageData['imageUrl'])) {
                $messageType = 'image';
                $lastMessageContent = '[Image]';
            } elseif (!empty($messageData['audio'])) {
                $messageType = 'audio';
                $lastMessageContent = '[Audio]';
            }

            // Count only unread messages where userId != chatId
            if (isset($messageData['seen']) && !$messageData['seen'] && isset($messageData['userId']) && $messageData['userId'] != $chatId) {
                $chatSummaries[$chatId]['unreadMessages']++;
            }

            // Get last message and its type
            if (!isset($chatSummaries[$chatId]['lastMessageTime']) || $messageData['createdAt'] > $chatSummaries[$chatId]['lastMessageTime']) {
                $chatSummaries[$chatId]['lastMessage'] = $lastMessageContent;
                $chatSummaries[$chatId]['lastMessageType'] = $messageType;
                $chatSummaries[$chatId]['lastMessageTime'] = $messageData['createdAt'] ?? null;
            }
        }

        return array_values($chatSummaries);
    }

    public function sendNotification($deviceToken, $title, $message, $type)
    {
        $serverKey = config('services.firebase.server_key');

        $data = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
            ],
            'data' => [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'status' => 'done',
                'type' => $type, // إرسال النوع
            ],
        ];

        Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $data);
    }


    public function markMessagesAsSeen($chatId)
    {
        $chatRef = $this->firestore->collection('chats')->document($chatId);
        $messagesCollection = $chatRef->collection('messages')->documents();

        foreach ($messagesCollection as $messageDoc) {
            if (!$messageDoc->exists()) {
                continue;
            }

            $messageData = $messageDoc->data();

            if (isset($messageData['seen']) && !$messageData['seen']) {
                $messageDoc->reference()->update([
                    ['path' => 'seen', 'value' => true]
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'All messages marked as seen for chat: ' . $chatId
        ]);
    }

    public function sendChatNotification($token, $messageData)
    {
        $template = NotificationTemplate::where('type', 'chat_message')->first();
        $title = $template->title ?? "New Message";
        $body = $template->message ?? "{message}";

        if (!empty($messageData['imageUrl'])) {
            $body = str_replace("{message}", "📷 New Image", $body);
        } elseif (!empty($messageData['audio'])) {
            $body = str_replace("{message}", "🎤 New Voice Message", $body);
        } elseif (!empty($messageData['message'])) {
            $body = str_replace("{message}", $messageData['message'], $body);
        } else {
            $body = str_replace("{message}", "📩 You have a new message", $body);
        }

        $notification = Notification::create($title, $body);
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'chat_id' => $messageData['id'] ?? "",
                'user_id' => $messageData['userId'] ?? "",
                'message' => $messageData['message'] ?? "",
                'imageUrl' => $messageData['imageUrl'] ?? "",
                'audio' => $messageData['audio'] ?? "",
            ]);

        return $this->messaging->send($message);
    }


}
