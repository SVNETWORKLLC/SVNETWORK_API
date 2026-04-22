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
use Illuminate\Support\Facades\DB;

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
          if ($payload->zipcode) {
            $zipcode = Zipcode::where('zipcode', $payload->zipcode)->first();
        }
        elseif ($payload->zipcode_id) {
            $zipcode = Zipcode::find($payload->zipcode_id);
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
        $maxMatches = 3;
        $request->validate(([
            'zipcode' => 'required',
            'service_id' => 'required',
            'project_id' => 'required',
            'email' => 'required',
        ]));



        $zipcode = $request->zipcode;


        $zipcode = Zipcode::where('zipcode', $zipcode)->first();

        $zipcodesLocation = self::getGeoZipcodes($zipcode->lon, $zipcode->lat, 50000);
        $zipcodesLocationIds = collect($zipcodesLocation)->pluck('idzipcode')->toArray();


        $service_id = $request->service_id;
        $project_id = $request->project_id;
        $project = Project::find($project_id);
        $service = Service::find($service_id);
        if (! $service) {
            return [];
        }

        $user = User::where('email', $request->email)->first();
        if (auth()->user()) {
            $user = auth()->user();
        }

        // Companies conditions
        // 1.- Non users repeated matches
        //$repeatedServiceMatches = Matches::where('service_id', $service_id)->where('email', $user->email)->whereDate('created_at', $today)->get();

        // if ($repeatedServiceMatches->count() > 0) {
        //     $companiesRepeated = $repeatedServiceMatches->map(function ($match) {
        //         return $match->company;
        //     });

        //     return [
        //         'zipcode' => [
        //             'location' => $zipcode->location,
        //             'state' => $zipcode->state,
        //             'zipcode' => $zipcode->zipcode,
        //         ],
        //         'message' => 'We found '.$repeatedServiceMatches->count().' companies that match the requested '.$service->name.' service.',
        //         'companies' => SearchCompanyResource::collection($companiesRepeated)->values(),
        //     ];
        // }

        // 2.- companies where service is paused
        // 3.-Companies without payment method
        // 4.-Companies not verified
        // 5.-Companies has more than defaults payments


        $matches = [];
        // implemnetar n8nservice
        $category = $service->category;

         //Match actions
        if(count($zipcodesLocationIds) == 0){
            $zipcodesLocationIds = [$zipcode->id];
        }

        $matches = collect();
        $ids = implode(',', $zipcodesLocationIds);
        if ($service) {
            $companies1 = $service->companyServiceZip()
                ->whereIn('zipcode_id', $zipcodesLocationIds)
                ->orderByRaw("FIELD(zipcode_id, $ids)")
                ->get();

            $matches = $matches->merge($companies1);
            $matches = $matches->unique('company_id');
            $servicesCategory = Service::where('category_id', $category->id)->where('id', '!=', $service->id)->get();
            if(count($matches) < 3 && count($servicesCategory) > 0){
                foreach ($servicesCategory as $serviceItem) {
                    $companies1 = $serviceItem->companyServiceZip
                    ->whereIn('zipcode_id', $zipcodesLocationIds);
                    $matches = $matches->merge($companies1);
                    $matches = $matches->unique('company_id');
                }
            }

        }


        $matches_array = [];
        $sortedMatches = [];
        $matches = $matches->unique('company_id');
        if (count($matches)) {
            foreach ($matches as $match) {
                $companyData = Company::find($match->company_id);
                if ($companyData) {
                    $matches_array[] = new SearchCompanyResource($companyData);
                }
            }

            //Order matches by verified first
            $sortedMatches = collect($matches_array);
            $verifiedMatches = $sortedMatches->where('verified', 1)->values();
            $unverifiedMatches = $sortedMatches->where('verified', 0)->values();

            $sortedMatches = $verifiedMatches->merge($unverifiedMatches)->values();

            //Get top 3 matches randomly
            $sortedMatches = $sortedMatches->take(3);

        }

        if(count($sortedMatches) == 0){
            $nomatch = NoMatches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'service_id' => $service->id,
            ]);
            $data = [
                'user' => $user,
                'service' => $service,
                'description' => $project->description,
                'zipcode' => $zipcode,
            ];

            $admins = User::where('is_admin', 1)->get();
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

        foreach ($sortedMatches as $company){
            $company->projects()->attach($project_id);
            $match = Matches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'project_id' => $project_id,
                'service_id' => $service_id,
            ]);

            try {

                $user_link = config('app.app_url').'/user/companies/profile/leads/'.$project_id.'/'.$match->id;
                $link = null;

                if($company->is_claimed == 0){
                    $link = $company->generateClaimUrl();
                }
                //notification to company admins
                $companyAdmins = $company->users;
                foreach ($companyAdmins as $admin) {
                    $admin->notify(new MatchesCompanyAiNotification($project, $link));
                }
            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                Log::error('Error occurred: '.$e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        try {
            $user->notify(new MatchesUserNotification(['matches' => $sortedMatches, 'service' => $service]));
        } catch (\Exception $e) {
            // Capturar el error y almacenarlo en el archivo de log
            Log::error('Error occurred: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $matchesValues = collect($sortedMatches)->values();

        return [
            'zipcode' => [
                'location' => $zipcode->location,
                'state' => $zipcode->state,
                'zipcode' => $zipcode->zipcode,
            ],
            'message' => 'We found '.count($matches_array).' companies that match the requested service in '. $zipcode->zipcode. ' '. $zipcode->location.', '.$zipcode->state.'.',
            'companies' => $matchesValues,
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
        $zipcodesLocation = self::getGeoZipcodes($zipcode->lon, $zipcode->lat, 50000);
        $zipcodesLocationIds = collect($zipcodesLocation)->pluck('idzipcode')->toArray();


        $project_id = $request->project_id;
        $project = Project::find($request->project_id);
        $searchText = $request->text;
        $matches = [];
        // implemnetar n8nservice
        try {
            $n8nService = new \App\Services\N8nService;
            $response = $n8nService->send([
                'text' => $searchText
            ]);
        } catch (\Exception $e) {
            Log::error('Error N8N Service: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

        }

        $serviceIds = $response['data']['output']['service_ids'] ?? null;
        $categoryIds = $response['data']['output']['categories'] ?? null;

        if (count($zipcodesLocationIds) == 0) {
            $zipcodesLocationIds = [$zipcode->id];
        }

        foreach ($serviceIds as $serviceId) {
            $service = Service::find($serviceId);
            if ($service && $service->category_id) {
                $categoryIds[] = $service->category_id;
            }
        }
        // remove duplicates
        $categoryIds = array_unique(array_filter($categoryIds));

        // get all services in those categories and merge into a unique service ids list
        $categoryServiceIds = Service::whereIn('category_id', $categoryIds)->pluck('id')->toArray();
        $serviceIds = array_values(array_unique(array_merge($serviceIds, $categoryServiceIds)));


        $matches = collect();
        $processedServiceIds = [];
        $ids = implode(',', $zipcodesLocationIds);
        foreach ($serviceIds as $serviceId) {
            $service = Service::find($serviceId);
            if ($service) {
                $companies1 = $service->companyServiceZip()
                ->whereIn('zipcode_id', $zipcodesLocationIds)
                ->orderByRaw("FIELD(zipcode_id, $ids)")
                ->get();

                $matches = $matches->merge($companies1);
                $matches = $matches->unique('company_id')->values();
                $processedServiceIds[] = $service->id;
            }
        }

        // $servicesCategory = Service::whereIn('category_id', $categoryIds)
        //     ->whereNotIn('id', $processedServiceIds)
        //     ->get();
        // if ($matches->count() < 3 && count($servicesCategory) > 0) {
        //     foreach ($servicesCategory as $serviceItem) {
        //         $companies2 = $serviceItem->companyServiceZip()
        //         ->whereIn('zipcode_id', $zipcodesLocationIds)
        //         ->orderByRaw("FIELD(zipcode_id, $ids)")
        //         ->get();

        //         $matches2 = $matches2->merge($companies2);
        //         $processedServiceIds[] = $service->id;
        //     }
        // }

        $matches = $matches->unique('company_id')->values();

        $admins = User::where('is_admin', 1)->get();
        $sortedMatches = [];

        if(count($matches) == 0){
             $serviceCustom = Service::updateOrCreate([
                'name' => 'AI Search',
            ], [
                'description' => 'Service found by AI',
                'price' => 0,
            ]);
            $selectedSeervice = Service::find($serviceIds[0] ?? $serviceCustom->id);

            NoMatches::create([
                'email' => $user->email,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'service_id' => $selectedSeervice->id,
            ]);
            $data = [
                'user' => $user,
                'service' => $selectedSeervice,
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
            return [
                'zipcode' => [
                    'location' => $zipcode->location,
                    'state' => $zipcode->state,
                    'zipcode' => $zipcode->zipcode,
                ],
                'message' => 'We found 0 companies that match the requested service.',
                'companies' => [],
            ];
        }

        if (count($matches)) {
            foreach ($matches as $match) {
                $companyData = Company::find($match->company_id);
                if ($companyData) {
                    $matches_array[] = new SearchCompanyResource($companyData);
                }
            }

            //Order matches by verified first
            $sortedMatches = collect($matches_array);
            $verifiedMatches = $sortedMatches->where('verified', 1)->values();
            $unverifiedMatches = $sortedMatches->where('verified', 0)->values();

            $sortedMatches = $verifiedMatches->merge($unverifiedMatches)->values();
            // $sortedMatches = $sortedMatches->take(3);
            $service_id = $serviceIds[0];
            foreach ($sortedMatches as $company) {
                $company->projects()->attach($project_id);
                $match = Matches::create([
                    'email' => $user->email,
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'project_id' => $project_id,
                    'service_id' => $service_id,
                ]);

                try {
                    $user->link = config('app.app_url').'/user/companies/profile/leads/'.$project_id.'/'.$match->id;

                    $link = null;

                    if($company->is_claimed == 0){
                        $link = $company->generateClaimUrl();
                    }
                    //notification to company admins
                    $companyAdmins = $company->users;
                    foreach ($companyAdmins as $admin) {
                        $admin->notify(new MatchesCompanyAiNotification($project, $link));
                    }
                } catch (\Exception $e) {
                    // Capturar el error y almacenarlo en el archivo de log
                    Log::error('Error occurred: '.$e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

        }


        //notification to user
        if (count($sortedMatches) > 0) {
            try {
                $user->notify(new MatchesUserNotification(['matches' => $sortedMatches, 'service' => $service]));
            } catch (\Exception $e) {
                // Capturar el error y almacenarlo en el archivo de log
                Log::error('Error occurred: '.$e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        $matchesValues = collect($sortedMatches)->values();
        return [
            'zipcode' => [
                'location' => $zipcode->location,
                'state' => $zipcode->state,
                'zipcode' => $zipcode->zipcode,
            ],
            'message' => 'We found '.count($sortedMatches).' companies that match the requested service in '. $zipcode->zipcode. ' '. $zipcode->location.', '.$zipcode->state.'.',
            'companies' => $matchesValues,
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

    private function getGeoZipcodes($lon, $lat, $distance = 10000){
        $query = <<<SQL
        SELECT *
        FROM (
            SELECT *,
                ST_Distance(
                    geopoint,
                    ST_SetSRID(ST_MakePoint($lon, $lat), 4326)::geography
                ) AS distance
            FROM public.zipcodes
            WHERE ST_DWithin(
                geopoint,
                ST_SetSRID(ST_MakePoint($lon, $lat), 4326)::geography,
                $distance
            )
        ) t
        ORDER BY distance ASC;
        SQL;
        try{
            $zipcodes = DB::connection('pgsql')
            ->select($query);
        }catch (\Exception $e) {
            Log::error('Error occurred: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }

        return $zipcodes;
    }
}
