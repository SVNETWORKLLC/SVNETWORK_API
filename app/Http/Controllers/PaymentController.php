<?php

namespace App\Http\Controllers;

use App\Http\Resources\BalanceResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\UserMinimalResource;
use App\Models\Company;
use App\Models\Service;
use App\Models\Transactions;
use Exception;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\Cast\Object_;
use Stripe\Balance;
use Carbon\Carbon;

class PaymentController extends Controller
{
public function getMethodCard()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();
        if ($user->stripe_client_id) {
            $customer = $stripe->customers->retrieve($user->stripe_client_id);
            $defaultPaymentMethodId = $customer->invoice_settings?->default_payment_method;


            $methods = $stripe->paymentMethods->all([
                'customer' => $user->stripe_client_id,
                'type' => 'card',
            ]);
            $methods2 = [];
            $methods2 = array_map(function($method)use($defaultPaymentMethodId){
                if($defaultPaymentMethodId == $method->id){
                    $method->default = true;
                }else{
                    $method->default = false;
                }
                return $method;
            }, $methods['data']);
            $methods['data'] = $methods2;
            return $methods;
        } else {
            return null;
        }
    }
    public function adminGetPaymentsMethodsCompany(Company $company)
    {

        $users = $company->users;
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $methodsList = $users->map(function($user) use ($stripe){
            if ($user->stripe_client_id) {
                try{
                    $methods = $stripe->paymentMethods->all([
                        'customer' => $user->stripe_client_id,
                        'type' => 'card',
                    ]);
                    return [
                        'user' => new UserMinimalResource($user),
                        'methods' => $methods
                    ];
                }catch(Exception $e){

                }
            }else {
                return null;
            }
        });
        $methods =  $methodsList->filter(function ($value) {
            return !is_null($value);
        });
        return $methods->values();

    }
    public function getCharges()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();

        if ($user->stripe_client_id) {
            try {
                $charges = $stripe?->charges->all([
                    'customer' => $user->stripe_client_id,
                ]);
            } catch (Exception $e) {
            }
            return new PaymentResource($charges);
        } else {
            return null;
        }
    }
    public function checkout()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $checkout_session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'mode' => 'setup',
            'customer' => 'cus_Pl1B6MehO6aoIV',
            'ui_mode' => 'embedded',
            'return_url' => 'http://localhost:8004/payments/checkout?session_id={CHECKOUT_SESSION_ID}',
        ]);
        //   $cliente = \Stripe\Customer::update(
        //     'cus_Pl1B6MehO6aoIV',
        //     ['invoice_settings' => ['default_payment_method' => $metodo_pago_id]]
        //   );
        return $checkout_session;
    }

    public function customCard()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();
        $intent = $stripe->setupIntents->create([
            'customer' => $user->stripe_client_id,
            'payment_method_types' => ['card'],
            "usage" => "off_session"
        ]);

        return $intent;
    }

    public function addPaymentMethod($setup)
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        return $stripe->setupIntents->retrieve('seti_1EzVO3HssDVaQm2PJjXHmLlM', []);
    }
    public function retrieveSession($id)
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $session =  $stripe->checkout->sessions->retrieve(
            $id,
            []
        );
        $setup =  $stripe->setupIntents->retrieve($session->setup_intent, []);

        // $stripe->paymentMethods->attach(
        //     $setup->payment_method,
        //     ['customer' => $setup->customer]
        // );
        return $setup;
    }

    public function recharge(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required'
        ]);

        $transaction = Transactions::find($request->transaction_id);
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        try {
            $user = $transaction->user;
            $service = $transaction->service;
            $stripe_client_id = $user->stripe_client_id;
            $payment = $stripe->paymentIntents->create([
                'amount' => $transaction->price  * 100,
                'currency' => 'usd',
                'customer' => $stripe_client_id,
                'payment_method' => $transaction->stripe_payment_method,
                'confirm' => true,
                'description' => 'Match ' . $service->name,
                'confirmation_method' => 'automatic', // Utiliza 'automatic' para pagos automáticos
                'metadata' => [
                    'customer_name' => $user->name . ' ' . $user->surname,
                    // Agrega más metadatos según sea necesario
                ],
                'return_url' => config('app.app_url') . '/user/companies/profile'
            ]);

            $transaction->paid = 1;
            $transaction->message = null;
            $transaction->payment_code = null;
            $transaction->save();
            return 'Ok';
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $status = false;
            $payment_message = $e->getError()->message;
            $payment_code = $e->getError()->decline_code;

            $transaction->message = $payment_message;
            $transaction->payment_code = $payment_code;
            $transaction->save();
            abort(422, $payment_message);
        }
    }

    public function retrieveIntent($id)
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));

        $setup =  $stripe->setupIntents->retrieve($id, []);

        // $stripe->paymentMethods->attach(
        //     $setup->payment_method,
        //     ['customer' => $setup->customer]
        // );
        return $setup;
    }

    public function payment()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $payment = $stripe->paymentIntents->create([
            'amount' => 2000,
            'currency' => 'usd',
            'customer' => 'cus_Pl1B6MehO6aoIV',
            'payment_method' => 'pm_1P1oDQL4tJJe6uDw7H3JfkwU',
            'confirm' => true,
            'confirmation_method' => 'automatic', // Utiliza 'automatic' para pagos automáticos
            'return_url' => 'https://example.com/success'
        ]);

        return $payment;
    }
    public function getCustomer()
    {

        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $charges = $stripe->charges->all([
            'customer' => 'cus_PZnSg9mitjTacZ',
        ]);

        return $charges;
    }

    public function getAllCharges()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();


        try {
            $charges = $stripe?->charges->all();
        } catch (Exception $e) {
        }
        return new PaymentResource($charges);
    }
    public function getBalance()
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
        $user = auth()->user();
        $balance = $stripe->balance->retrieve([]);
        return new BalanceResource($balance);
    }

    public function totalWeek()
    {
        $startOfWeek = Carbon::now()->startOfWeek()->toDateTimeString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateTimeString();
        $user = auth()->user();
        $transactions = $user->transactions->where('paid', 1)->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
        $startOfWeek = Carbon::now()->startOfWeek()->format('M-d H:i:s');
        $endOfWeek = Carbon::now()->endOfWeek()->format('M-d H:i:s');
        $total = $transactions->sum('price').' US$';
        return [
            'start_week' => $startOfWeek,
            'end_week' => $endOfWeek,
            'total' => $total
        ];
    }
}
