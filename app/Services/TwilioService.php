<?php
namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));
    }

    public function sendVerificationCode($toPhoneNumber, $verificationCode)
    {
        $messageBody = "Welcome to ".config('app.name'). "\n Your verification code is: " . $verificationCode;

        $message = $this->twilio->messages->create($toPhoneNumber, [
            'messagingServiceSid' => config('services.twilio.messaging_service_sid'),
            'body' => $messageBody,
        ]);
    }
}
?>
