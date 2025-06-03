<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
use App\Models\PushNotification;

class PushNotificationController extends Controller
{
    /**
     * Envía una notificación push FCM a todos los tokens de un usuario
     *
     * @param int|User $userIdOrUser
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $imageUrl
     * @param string|null $clickAction
     * @return array
     */
    public static function sendPushNotification($userIdOrUser, $title, $body, $data = [], $imageUrl = null, $clickAction = null)
    {
        $credentialsPath = storage_path('app/firebase-credentials.json');
        $factory = (new Factory)
            ->withServiceAccount($credentialsPath);
        $messaging = $factory->createMessaging();

        $notification = Notification::create($title, $body);
        if ($imageUrl) {
            $data['image'] = $imageUrl;
        }
        if ($clickAction) {
            $data['click_action'] = $clickAction;
        }

        // Obtener usuario y tokens
        $user = User::findOrFail($userIdOrUser);

        $tokens = $user->pushNotifications()->pluck('token');
        $results = [];
        foreach ($tokens as $fcmToken) {
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data)
                ->toToken($fcmToken);
            try {
                $results[$fcmToken] = $messaging->send($message);
            } catch (MessagingException $e) {
                $results[$fcmToken] = 'Error: ' . $e->getMessage();
            }
        }
        return $results;
    }

    /**
     * Endpoint para enviar una notificación push FCM a un token específico
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendPushNotificationEndpoint(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'array',
            'image_url' => 'nullable|string',
            'click_action' => 'nullable|string',
        ]);


        $title = $request->input('title');
        $body = $request->input('body');
        $data = $request->input('data', []);
        $imageUrl = $request->input('image_url');
        $clickAction = $request->input('click_action');

        $result = self::sendPushNotification(1, $title, $body, $data, $imageUrl, $clickAction);

        return response()->json(['result' => $result]);
    }
}
