<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function __construct()
    {
        //        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        //        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verifyEmail(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(401);
        }

        $userId = $request->input('user');
        $user = User::findOrFail($userId);

        // if($user->email_verified_at !== null){
        //     abort(422,'Este email ya ha sido verificado');
        // }

        $user->markEmailAsVerified();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource(auth()->user());

    }

    public function resend(Request $request, string $email): JsonResponse
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json('No existen datos para este email', 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json('El usuario ya ha sido verificado');
        }

        $user->redirect = config('app.web_url');

        $verifyUrl = URL::temporarySignedRoute(
            'auth.verify',
            now()->addMinutes(60),
            [
                'user' => $user->id,
            ],
        );

        // Remplazamos la url de la api por la url de la app
        $api_url = config('app.url').'/app/';
        $web_url = config('app.web_url');
        $url = str_replace($api_url, $web_url, $verifyUrl);
        $link = strval($url);
        $user->link = $link;

        //  event(new Registered($user));

        return response()->json('Se ha enviado el email de verificación');
    }

    public function claimCompany(Request $request)
    {

        if (! $request->hasValidSignature()) {
            abort(401);
        }

        $userId = $request->input('user');
        $user = User::findOrFail($userId);
    }
}
