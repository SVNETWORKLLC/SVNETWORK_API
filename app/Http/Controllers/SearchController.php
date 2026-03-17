<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanySearchResource;
use App\Http\Resources\MatchesResource;
use App\Http\Resources\NoMatchesResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SearchCompanyResource;
use App\Models\AnswerProject;
use App\Models\Company;
use App\Models\CompanyService;
use App\Models\Matches;
use App\Models\NoMatches;
use App\Models\Project;
use App\Models\Question;
use App\Models\Service;
use App\Models\Transactions;
use App\Models\User;
use App\Models\Zipcode;
use App\Notifications\MatchesCompanyAiNotification;
use App\Notifications\MatchesCompanyNotification;
use App\Notifications\MatchesUserNotification;
use App\Notifications\NoMatchesAdminNotification;
use App\Notifications\SendLeadNotification;
use App\Notifications\SendLeadToCompanyNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    public function searchForm(Request $request)
    {
        $files = $request->file('images');
        $rawData = $request->input('data');
        $payload = is_string($rawData)
            ? json_decode($rawData)
            : json_decode(json_encode($rawData ?? []));

        $user = User::where('email', $payload->user_data->email)->first();
        if (! $user) {
            $user = User::create([
                'name' => $payload->user_data->name ?? null,
                'surname' => $payload->user_data->lastname ?? null,
                'email' => $payload->user_data->email ?? null,
                'phone' => $payload->user_data->phone ?? null,
            ]);
        }
        $service = null;
        if (isset($payload->service_id) && $payload->service_id) {
            $service = Service::find($payload->service_id);
            if (! $service) {
                abort(422, 'Service not found');
            }
        }

        $zipcode = null;
        if ($payload->zipcode_id) {
            $zipcode = Zipcode::find($payload->zipcode_id);
        }
        if ($payload->zipcode) {
            $zipcode = Zipcode::where('zipcode', $payload->zipcode)->first();
        }

        $title = ($service ? $service->name : 'Service').' in '.$zipcode->location.', '.$zipcode->state.' '.$zipcode->zipcode;
        $project = $user->projects()->create([
            'description' => $payload->project_details,
            'service_id' => $service?->id,
            'title' => $title,
            'zipcode_id' => $zipcode->id,
            'state_iso' => $zipcode->state_iso,
        ]);

        if ($request->hasFile('images')) {
            foreach ($files as $image) {
                $filename = $project->uuid.'/image-'.uniqid().'.'.$image->extension();
                Storage::disk('projects')->put($filename, file_get_contents($image));
                $extension = $image->extension();
                $size = $image->getSize();
                $mimetype = $image->getMimeType();

                $image = $project->images()->create([
                    'filename' => $filename,
                    'mime_type' => $mimetype,
                    'extension' => $extension,
                    'type' => 1,
                    'size' => $size,
                ]);
            }
        }

        foreach ($payload->answers as $answer) {
            $question = Question::find($answer->question_id);

            AnswerProject::create([
                'answer_id' => $answer->answer_id ?? null,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'question_text' => $question->text,
                'text' => $answer->text ?? 'N/A',
            ]);
        }

        return $project;
    }

    public function search(Request $request)
    {
        $request->validate(([
            'zipcode' => 'required',
            'service_id' => 'required',
            'project_id' => 'required',
            'email' => 'required',
        ]));

        $zipcode = $request->zipcode;
        $service_id = $request->service_id;
        $project_id = $request->project_id;
        $project = Project::find($project_id);
        $service = Service::find($service_id);
        if (! $service) {
            return [];
        }
        $price = $service?->price > 0 ? round($service->price * 100, 0) : 0;

        $user = User::where('email', $request->email)->first();
        $admins = User::where('is_admin', 1)->get();
        if (auth()->user()) {
            $user = auth()->user();
        }
        $zipcode = Zipcode::where('zipcode', $zipcode)->first();
        // Companies conditions
        // 1.- Non users repeated matches
        $today = Carbon::today();
        $repeatedServiceMatches = Matches::where('service_id', $service_id)->where('email', $user->email)->whereDate('created_at', $today)->get();

        if ($repeatedServiceMatches->count() > 0) {
            $companiesRepeated = $repeatedServiceMatches->map(function ($match) {
                return $match->company;
            });

            return [
                'zipcode' => [
                    'location' => $zipcode->location,
                    'state' => $zipcode->state,
                    'zipcode' => $zipcode->zipcode,
                ],
                'message' => 'We found '.$repeatedServiceMatches->count().' companies that match the requested '.$service->name.' service.',
                'companies' => SearchCompanyResource::collection($companiesRepeated)->values(),
            ];
        }

        // 2.- companies where service is paused
        $companiesServicePause = CompanyService::where('service_id', $service_id)->where('pause', 1)->pluck('company_id');
        $companies = Company::all();

        // 3.-Companies without payment method
        $companiesWithoutPaymentMethod = $companies->map(function ($company) {
            if ($company->users->count()) {
                return $company->users->first()->stripe_client_id != null ? $company->id : null;
            }
        })->whereNotNull()->toArray();
        // 4.-Companies not verified
        $companiesNotVerified = $companies->map(function ($company) {
            return $company->verified == 0 ? $company->id : null;
        })->whereNotNull()->toArray();

        // 5.-Companies has more than defaults payments
        $companiesDefaults = Transactions::selectRaw('company_id, COUNT(*) as count')
            ->where('paid', 0)
            ->groupBy('company_id')
            ->get();

        $companiesDefaults = $companiesDefaults->map(function ($row) {
            return $row->count >= 5 ? $row->company_id : null;
        })->whereNotNull()->toArray();

        $matches = $service->companyServiceZip
            ->where('zipcode_id', $zipcode->id)
            ->whereNotIn('company_id', $companiesNotVerified)
            // ->whereNotIn('company_id', $companiesMatchIds)
            ->whereNotIn('company_id', $companiesServicePause)
            // ->whereNotIn('company_id', $companiesDefaults)
            // ->whereIn('company_id', $companiesWithoutPaymentMethod)
            ->take(3);

        // No matches actions
        if (count($matches) == 0) {

            // if ($companiesMatchIds) {
            //     abort(422, 'No matches');
            // }
            $nomatch = NoMatches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'project_id' => $project_id,
                'service_id' => $service_id,
            ]);
            $data = [
                'user' => $user,
                'service' => $service,
                'zipcode' => $zipcode,
            ];
            foreach ($admins as $key => $admin) {
                try {
                    $admin->notify(new NoMatchesAdminNotification($data));
                } catch (\Exception $e) {
                    // Capturar el error y almacenarlo en el archivo de log
                    Log::error('Error occurred: '.$e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return $nomatch;
        }

        $matches_array = [];
        $matches = $matches->map(function ($match) use ($service_id, $user, $project_id, $service) {

            $payment_message = null;
            $status = false;
            $payment_code = null;

            // Envio cobro a compania en caso de que sea verificada y creada por usuario
            $company = Company::find($match->company->id);
            $company->projects()->attach($project_id);
            $payment = null;
            // if ($match->company->users) {

            //     $payment_method_id = null;
            //     if ($match->company->users[0]->stripe_client_id) {

            //         $stripe = new \Stripe\StripeClient(config('app.stripe_pk'));
            //         $payment_methods = $stripe->paymentMethods->all([
            //             'type' => 'card',
            //             'limit' => 3,
            //             'customer' => $match->company->users[0]->stripe_client_id,
            //         ]);

            //         if ($payment_methods->data) {
            //             $payment_method_id = $payment_methods->data[0]->id;

            //             try {
            //                 if ($service->price > 0) {

            //                     $payment = $stripe->paymentIntents->create([
            //                         'amount' => $service->price  * 100,
            //                         'currency' => 'usd',
            //                         'customer' => $match->company->users[0]->stripe_client_id,
            //                         'payment_method' => $payment_method_id,
            //                         'confirm' => true,
            //                         'description' => 'Match ' . $service->name,
            //                         'confirmation_method' => 'automatic', // Utiliza 'automatic' para pagos automáticos
            //                         'metadata' => [
            //                             'customer_name' => $match->company->users[0]->name . ' ' . $match->company->users[0]->surname,
            //                             // Agrega más metadatos según sea necesario
            //                         ],
            //                         'return_url' => config('app.app_url') . '/user/companies/profile'
            //                     ]);
            //                 }

            //                 $status = true;
            //                 Transactions::create([
            //                     'user_id' => $match->company->users[0]->id,
            //                     'project_id' => $project_id,
            //                     'service_id' => $service->id,
            //                     'stripe_payment_method' => $payment_method_id,
            //                     'price' => $service->price,
            //                     'company_id' => $match->company->id,
            //                     'paid' => $status,
            //                     'message' => null,
            //                     'match_id' => $match->id,
            //                     'stripe_payment_intent' => $payment->id,
            //                     'payment_code' => $payment_code,
            //                 ]);
            //             } catch (\Stripe\Exception\ApiErrorException $e) {
            //                 Log::error('Error matches: ' . $e->getMessage(), [
            //                     'exception' => $e,
            //                     'trace' => $e->getTraceAsString(),
            //                 ]);
            //                 $status = false;
            //                 $payment_message = $e->getError()->message;
            //                 $payment_code = $e->getError()->decline_code;

            //                 // Maneja el error de Stripe aquí
            //                 // Puedes registrar el error, mostrar un mensaje al usuario, etc.
            //                 // Pero el código continuará ejecutándose después de este bloque catch
            //                 if ($payment_method_id) {

            //                     Transactions::create([
            //                         'user_id' => $match->company->users[0]->id,
            //                         'project_id' => $project_id,
            //                         'service_id' => $service->id,
            //                         'company_id' => $match->company->id,
            //                         'stripe_payment_method' => $payment_method_id,
            //                         'price' => $service->price,
            //                         'paid' => $status,
            //                         'match_id' => $match->id,
            //                         'message' => $payment_message,
            //                         'payment_code' => $payment_code,
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
            // }

            $match = Matches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'company_id' => $match->company->id,
                'project_id' => $project_id,
                'service_id' => $service_id,
            ]);

            try {
                $user->link = config('app.app_url').'/user/companies/profile/leads/'.$project_id.'/'.$match->id;
                $user->service = $service;
                $match->company->users[0]->notify(new MatchesCompanyNotification($user));
            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                Log::error('Error occurred: '.$e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return new SearchCompanyResource($company);
        }); // matches map

        $matches = $matches->filter(function ($value) {
            return ! is_null($value);
        });

        if (count($matches)) {
            $data = ['matches' => $matches, 'service' => $service];
            try {
                $user->notify(new MatchesUserNotification($data));
            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                Log::error('Error occurred: '.$e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return [
            'zipcode' => [
                'location' => $zipcode->location,
                'state' => $zipcode->state,
                'zipcode' => $zipcode->zipcode,
            ],
            'message' => 'We found '.count($matches).' companies that match the requested '.$service->name.' service.',
            'companies' => $matches->values(),
        ];
    }

    public function searchAi(Request $request)
    {
        $request->validate(([
            'zipcode' => 'required',
            'email' => 'required',
            'text' => 'required',
            'project_id' => 'required',
        ]));
        $user = User::where('email', $request->email)->first();
        if (auth()->user()) {
            $user = auth()->user();
        }
        $zipcode = Zipcode::where('zipcode', $request->zipcode)->first();
        $project = Project::find($request->project_id);
        $locationText = " My default location is:". $zipcode->location.', '.$zipcode->state.' '.$zipcode->zipcode. " Region is: ".$zipcode->region;
        $matches = [];
        // implemnetar n8nservice
        try {
            $n8nService = new \App\Services\N8nService;
            $response = $n8nService->send([
                'text' => $request->text . $locationText,
                'user_id' => $user->uuid,
            ]);
            $message = $response['data']['output']['message'] ?? null;
            $service_id = $response['data']['output']['service_id'] ?? null;
            if ($service_id) {
                $service = Service::find($service_id);
            } else {
                $service = null;
            }
            foreach ($response['data']['output']['companies'] ?? [] as $company) {
                $selectedCompany = Company::find($company['companyId']);

                array_push($matches, new SearchCompanyResource($selectedCompany));
            }
        } catch (\Exception $e) {
            Log::error('Error N8N Service: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

        }
        $admins = User::where('is_admin', 1)->get();

        if (count($matches) == 0) {

            $serviceCustom = Service::updateOrCreate([
                'name' => $service ? $service->name : 'AI Search',
            ], [
                'description' => $service ? $service->description : 'Service found by AI',
                'price' => 0,
            ]);
            $nomatch = NoMatches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'service_id' => $serviceCustom->id,
            ]);
            $data = [
                'user' => $user,
                'service' => $serviceCustom,
                'description' => $project->description,
                'zipcode' => $zipcode,
            ];
            foreach ($admins as $key => $admin) {
                try {
                    $admin->notify(new NoMatchesAdminNotification($data));
                } catch (\Exception $e) {
                    // Capturar el error y almacenarlo en el archivo de log
                    Log::error('Error occurred: '.$e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return $nomatch;
        }
        $serviceCustom = Service::updateOrCreate([
            'name' => $service ? $service->name : 'AI Search',
        ], [
            'description' => $service ? $service->description : 'Service found by AI',
            'price' => 0,
        ]);

        foreach ($matches as $company) {
            $company->projects()->attach($project->id);
            $match = Matches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'service_id' => $serviceCustom->id,
            ]);

            try {
                $user->link = config('app.app_url').'/user/companies/profile/leads/'.$project->id.'/'.$match->id;
                $user->service = $serviceCustom;
                // $user->notify(new MatchesCompanyAiNotification($project));
            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                Log::error('Error occurred: '.$e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return [
            'zipcode' => [
                'location' => $zipcode->location,
                'state' => $zipcode->state,
                'zipcode' => $zipcode->zipcode,
            ],
            'message' => $message ?? 'We found '.count($matches).' companies that match the requested service.',
            'companies' => collect($matches)->values(),
        ];
    }

    public function searchCompanies(Request $request)
    {
        $request->validate(([
            'zipcode' => 'required',
            'service_id' => 'required',
        ]));

        $zipcode = $request->zipcode;
        $service_id = $request->service_id;
        $service = Service::find($service_id);
        if (! $service) {
            return [];
        }

        $zipcode = Zipcode::where('zipcode', $zipcode)->first();
        // Companies conditions

        // 2.- companies where service is paused
        $companiesServicePause = CompanyService::where('service_id', $service_id)->where('pause', 1)->pluck('company_id');
        $companies = Company::all();

        // 3.-Companies without payment method
        $companiesWithoutPaymentMethod = $companies->map(function ($company) {
            if ($company->users->count()) {
                return $company->users->first()->stripe_client_id != null ? $company->id : null;
            }
        })->whereNotNull()->toArray();
        // 4.-Companies not verified
        $companiesNotVerified = $companies->map(function ($company) {
            return $company->verified == 0 ? $company->id : null;
        })->whereNotNull()->toArray();

        // 5.-Companies has more than defaults payments
        $companiesDefaults = Transactions::selectRaw('company_id, COUNT(*) as count')
            ->where('paid', 0)
            ->groupBy('company_id')
            ->get();

        $companiesDefaults = $companiesDefaults->map(function ($row) {
            return $row->count >= 5 ? $row->company_id : null;
        })->whereNotNull()->toArray();

        $companies = $service->companyServiceZip
            ->where('zipcode_id', $zipcode->id);
        // ->whereNotIn('company_id', $companiesNotVerified)
        // ->whereNotIn('company_id', $companiesServicePause)
        // ->whereIn('company_id', $companiesWithoutPaymentMethod);

        return CompanySearchResource::collection($companies->values());
    }

    public function searchCustom(NoMatches $noMatches)
    {
        $noMatches->requested_lead = date('Y-m-d H:i:s');
        $noMatches->save();

        return 'ok';
    }

    public function noMatchesList()
    {
        $noMatches = NoMatches::where('done', 0)->orderBy('created_at', 'desc')->get();

        return NoMatchesResource::collection($noMatches);
    }

    public function matchesList()
    {
        $matches = Matches::orderBy('created_at', 'desc')->get();

        return MatchesResource::collection($matches);
    }

    public function updateNoMatches(NoMatches $noMatches)
    {
        $noMatches->done = true;
        $noMatches->save();

        return $noMatches;
    }

    public function sendLead(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'nomatch' => 'required',
            'email' => 'required',
        ]);
        $nomatch = NoMatches::find($request->nomatch);
        $service = Service::withTrashed()->find($nomatch->service_id);
        $servicesId = Project::pluck('service_id')->unique()->values();
        $servicesTrend = Service::whereIn('id', $servicesId)->take(6)->get();

        $nomatch->company_name = $request->name;
        $nomatch->company_phone = $request->phone;
        $nomatch->company_email = $request->email;
        $nomatch->message = $request->message ?? '';
        $nomatch->save();

        $data = [
            'company_name' => $request->name,
            'company_phone' => $request->phone,
            'company_address' => $request->address,
            'service' => $service,
            'services' => $servicesTrend,
            'email' => $request->email,
            'message' => $request->message ?? '',
        ];

        if ($nomatch) {
            $user = User::where('email', $nomatch->email)->first();
            if ($user) {
                $user->notify(new SendLeadNotification($data));
                $nomatch->done = 1;
                $nomatch->save();
                $project = Project::find($nomatch->project_id);
                $project = new ProjectResource($project);

                $project->company_name = $request->name;

                Notification::route('mail', $request->email)->notify(new SendLeadToCompanyNotification($project));
            }

            return 'ok';
        } else {
            abort(422, 'User dows not exist');
        }
    }
}
