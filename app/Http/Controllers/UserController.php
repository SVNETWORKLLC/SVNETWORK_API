<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardCompanyResource;
use App\Http\Resources\DashboardUserResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\UserCompanyResource;
use App\Http\Resources\UserProjectResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Project;
use App\Models\Matches;
use App\Models\Quote;
use App\Models\Service;
use App\Notifications\RefundRequestNotification;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Expr\Match_;

class UserController extends Controller
{
    public function list()
    {
        $users = User::where('is_admin', 0)->orderBy('updated_at', 'DESC')->get();
        return DashboardUserResource::collection($users);
    }
    public function listPro()
    {
        $users = User::where('pro', 1)->orderBy('updated_at', 'DESC')->get();
        return DashboardUserResource::collection($users);
    }
    public function show()
    {
        $user = auth()->user();
        return new UserResource($user);
    }
    public function showProject(Project $project)
    {
        $user = auth()->user();
        if($project->user_id != $user->id) {
            return abort(403, 'Unauthorized action.');
        }
        return new UserProjectResource($project);
    }
    public function showQuote(Quote $quote)
    {
        $user = auth()->user();
        if($quote->user_id != $user->id) {
            return abort(403, 'Unauthorized action.');
        }
        return new QuoteResource($quote);
    }
    public function company()
    {
        $user = auth()->user();

        $company = $user->companies->first();
        $company->services = $company->services->map(function ($service) {
            $service->states = $service->companyServiceState
                ->where('company_id', auth()->user()->companies->first()->id)
                ->map(function ($state) {
                    return $state->state;
                })
                ->sortBy('name_en'); // Ordenar por el atributo 'name'
            return $service;
        });

        if ($company) {
            return new UserCompanyResource($company);
        } else {
            return abort(404, 'Company not found');
        }
    }
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $user = auth()->user();
        $user->name =  $request->name;
        $user->surname =  $request->surname;

        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->filled('verified_phone')) {
            $user->verified_phone = $request->verified_phone;
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();
        return new UserResource($user);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'surname' => 'required',
            'email' => 'required'
        ]);

        $user = User::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'email_verified_at' => now(),
            'pro' => 1
        ]);

        return new UserResource($user);
    }
    public function requestRefund(Request $request)
    {
        $request->validate([
            'lead_id' => 'required',
            'reason' => 'required'
        ]);
        $lead = Matches::findOrFail($request->lead_id);
        $service = Service::findOrFail($lead->service_id);

        $user = auth()->user();
        $admins = User::where('is_admin', 1)->get();
        $company = Company::findOrFail($lead->company_id);

        $urlArchivo = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            if ($image->isValid()) {
                // Realizar acciones con cada imagen, como guardarla en el servidor

                $nombreArchivo = $company->id . '/refund/image-' . uniqid() . '.' . $image->extension();
                Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
                $urlArchivo = Storage::disk('companies')->url($nombreArchivo);
                $extension = $image->extension();
                $size = $image->getSize();
                $mimetype = $image->getMimeType();
                $ancho = null;
                $alto = null;
                $infoImagen = getimagesize($image);

                if ($infoImagen) {
                    $ancho = $infoImagen[0]; // Ancho de la imagen
                    $alto = $infoImagen[1]; // Alto de la imagen
                }
                $company->images()->create([
                    'filename' => $nombreArchivo,
                    'type' => Image::TYPE_REFUND,
                    'mime_type' => $mimetype,
                    'extension' => $extension,
                    'width' => $ancho,
                    'height' => $alto,
                    'size' => $size
                ]);
            }
        }

        $form = ['description' =>$request->description, 'reason'=>$request->reason];
        $data = [
            'service' => $service,
            'lead' => $lead,
            'user' => $user,
            'form' => $form,
            'image'=> $urlArchivo
        ];

        $lead->refund_status = Matches::REFUND_REQUESTED;
        $lead->save();

        foreach ($admins as $admin) {
            $admin->notify(new RefundRequestNotification($data));
        }
        return new UserResource($user);
    }
    public function storeGuess(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'surname' => 'required',
            'email' => 'required',
            'phone' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'surname' => $request->surname,
                'email' => $request->email,
                'phone' => $request->phone
            ]);
        }

        Auth::login($user);


        return new UserResource($user);
    }
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required'
        ]);

        $email = auth()->user()->email;
        $user = User::where('email', $email)->first();
        $user->password = bcrypt($request->password);
        $user->save();

        return $user;
    }

    public function storeImage(User $user, Request $request)
    {

        $request->validate([
            'image' => 'required'
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $nombreArchivo = $user->id . '/image-' . uniqid() . '.' . $image->extension();;
            Storage::disk('users')->put($nombreArchivo, file_get_contents($image));
            $urlArchivo = Storage::disk('users')->url($nombreArchivo);
            $user->image = $urlArchivo;
        }

        $user->save();

        return $user;
    }
    public function storeImageAuthUser(Request $request)
    {

        $request->validate([
            'image' => 'required'
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'
        $user = auth()->user();
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $nombreArchivo = $user->id . '/image-' . uniqid() . '.' . $image->extension();;
            Storage::disk('users')->put($nombreArchivo, file_get_contents($image));
            $urlArchivo = Storage::disk('users')->url($nombreArchivo);
            $user->image = $urlArchivo;
        }

        $user->save();

        return $user;
    }
    public function projects(User $user)
    {
        $user = User::first();
        return UserProjectResource::collection($user->projects);
    }

    function emailExist($email)
    {
        $exist = User::where("email", $email)->exists();
        return $exist;
    }
    function AdminEmailExist($email)
    {
        $exist = User::where("email", $email)->exists();
        return $exist;
    }
    function checkRobot(Request $request)
    {
        $client = new Client(); // Instancia de Guzzle
        $secret = config('app.recaptcha_secret'); // Clave secreta de reCAPTCHA
        $response = $request->input('response'); // Respuesta de reCAPTCHA



        $response = $client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', ['form_params' => ['secret' => $secret, 'response' => $response]]);

        $body = $response->getBody();
        $data = json_decode($body, true);

        return $data;
    }

    /**
     * Almacena el token FCM del usuario autenticado
     */
    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = auth()->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['message' => 'Token FCM guardado correctamente']);
    }
}
