<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationController extends Controller
{
    /**
     * Envía una notificación push FCM a un token específico
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return mixed
     */
    public static function sendPushNotification($fcmToken, $title, $body, $data = [])
    {
        $credentialsPath = storage_path('app/firebase-credentials.json');
        $factory = (new Factory)
            ->withServiceAccount($credentialsPath);
        $messaging = $factory->createMessaging();

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->toToken($fcmToken);

        try {
            $result = $messaging->send($message);
            return $result;
        } catch (MessagingException $e) {
            // Puedes loguear el error o manejarlo según tu necesidad
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            'fcm_token' => 'required|string',
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'array',
        ]);

        $fcmToken = $request->input('fcm_token');
        $title = $request->input('title');
        $body = $request->input('body');
        $data = $request->input('data', []);

        $result = self::sendPushNotification($fcmToken, $title, $body, $data);

        return response()->json(['result' => $result]);
    }
}
