<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function storeCard(Request $request)
    {
        $request->validate([
            'card' => 'required'
        ]);

        $user = auth()->user();

        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $paymentMethod = $stripe->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => 8,
                'exp_year' => 2026,
                'cvc' => '314',
            ],
        ]);
        try {
            $stripe->paymentMethods->attach(
                'pm_1MqM05LkdIwHu7ixlDxxO6Mc',
                ['customer' => $user->stripe_client_id]
            );
        } catch (Exception $e) {
        }

        return $paymentMethod;
    }
    public function deleteCard($id)
    {
        //Comprobar que el metodo pertenece al usuario;
        $user = auth()->user();
        $client_id = $user->stripe_client_id;
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $paymentMethod = $stripe->paymentMethods->detach(
            $id
        );


        return $paymentMethod;
    }

    public function setDefaultMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required'
        ]);
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();
        if (!$user->stripe_client_id) {
            abort(422, 'No id client');
        }
        try {
            $stripe->customers->update(
                $user->stripe_client_id,
                [
                    'invoice_settings' => [
                        'default_payment_method' => $request->payment_method_id,
                    ],
                ]
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            abort(422, 'Error');
        }
    }

    public function setLastMethodPaymentAsDefault()
    {
        $user = auth()->user();
        $user->setLastMethodPaymentAsDefault();
    }
}
