<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyServiceResource;
use App\Http\Resources\DashboardCompanyResource;
use App\Http\Resources\DashboardServicePricesResource;
use App\Http\Resources\DashboardServiceResource;
use App\Http\Resources\ServiceNewAddedResource;
use App\Http\Resources\ServicePublicResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\UserCompanyResource;
use App\Models\Company;
use App\Models\CompanyService;
use App\Models\CompanyServiceState;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\Zipcode;
use App\Models\CompanyServiceZip;
use App\Models\Project;
use App\Models\State;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function list()
    {
        $services = Cache::remember('services.list', now()->addHour(), fn() => Service::orderBy('name')->get());
        return ServiceResource::collection($services);
    }
    public function newAddedServices()
    {
        $services = CompanyServiceState::select('state_id', 'service_id')
            ->groupBy('state_id', 'service_id')
            ->orderBy('updated_at', 'desc')
            ->take(20)
            ->get();

        return ServiceNewAddedResource::collection($services);
    }
    public function top10(Request $request)
    {
        $zipcode = $request->query('zipcode');
        $zipcode = Zipcode::where('zipcode', $zipcode)->first();
        $results = collect([]);

        //Zipcode == project
        $projectsZip = Project::where('zipcode_id', $zipcode?->id)
            ->orderBy('id', 'desc')
            ->get();

        $results = $results->concat($projectsZip->pluck('service_id'))->unique();

        //State iso == project
        if ($results->count() < 10) {
            $stateZipcodes = Zipcode::where('state_iso', $zipcode?->state_iso)->get()->pluck('id');
            $projectsState = Project::whereIn('zipcode_id', $stateZipcodes)->whereNotIn('id', $projectsZip->pluck('id'))
                ->orderBy('id', 'desc')
                ->get();
            $results = $results->concat($projectsState->pluck('service_id'))->unique();
        }

        // Any project
        if ($results->count() < 10) {
            $projectsAll = Project::whereNotIn('id', $projectsZip->pluck('id'))->whereNotIn('id', $projectsState->pluck('id'))
                ->orderBy('id', 'desc')
                ->get();
            $results = $results->concat($projectsAll->pluck('service_id'))->unique();
        }

        //Any Service
        if ($results->count() < 10) {
            $servicesAll = Service::whereNotIn('id', $results)->orderBy('id', 'desc')->get();
            $results = $results->concat($servicesAll->pluck('id'))->unique();
        }

        $results = $results->values();
        $services = $results->map(function ($service) {
            return Service::find($service);
        })->filter(function ($service) {
            return !is_null($service);
        })->values();

        return ServiceResource::collection($services->take(8));
    }
    public function prices()
    {
        $services = Service::all();
        return DashboardServicePricesResource::collection($services);
    }
    public function addService(Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $company = Company::find($request->company_id);
        $service = Service::find($request->service);
        $company->services()->syncWithoutDetaching([
            $request->service => [
                'pause' => 1
            ]
        ]);

        return new DashboardCompanyResource($company);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $service = Service::firstOrCreate([
            'name' => $request->name
        ]);

        // if($request->filled('category_id')){
        //     $service->category_id = $request->category_id;
        // }

        // if($request->filled('description')){
        //     $service->description = $request->description;
        //     $service->save();
        // }

        return $service;
    }
    public function adminStoreState(Request $request)
    {
        $request->validate([
            'service_id' => 'required',
            'state_id' => 'required',
            'company_id' => 'required'
        ]);
        $company = Company::findOrFail($request->company_id);
        $service = Service::findOrFail($request->service_id);

        $existing = $service->states()
            ->wherePivot('state_id', $request->state_id)
            ->wherePivot('company_id', $request->company_id)
            ->exists();

        if (!$existing) {
            $service->states()->attach([
                $request->state_id => ["company_id" => $request->company_id]
            ]);
        }

        $company_id = $request->company_id;
        $states = $service->states()->where('company_id', $company_id)->get();
        $states->map(function ($state) use ($service, $company_id) {
            $state->regions = $state->regions()->map(function ($region) use ($service, $company_id, $state) {

                $serviceZipCodes = CompanyServiceZip::where('service_id', $service->id)
                    ->where('company_id', $company_id)->where('region_text', $region)
                    ->where('state_iso', $state->iso_code)
                    ->get();
                $serviceZipCodes = $serviceZipCodes->map(function ($zipcodes) {
                    return $zipcodes->zipcode;
                });

                return ["name" => $region["name"], "zipcodes" => $serviceZipCodes, "zipTotal" => count($serviceZipCodes)];
            });

            $state->areasTotal = 0;
            return $state;
        });

        $service->states = $states;

        return new CompanyServiceResource($service);
    }
    public function adminStore(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service' => 'required',
        ]);

        $company = Company::find($request->company_id);
        $service = Service::find($request->service);
        $company->services()->syncWithoutDetaching([
            $request->service => [
                'pause' => 0
            ]
        ]);

        if (isset($request->copyStatesFromOthers) && $request->copyStatesFromOthers == true) {
            self::copyStatesByService($company->id, $request->service);
        }

        return new UserCompanyResource($company);
    }

    protected function copyStatesByService($company_id, $newServiceId = null)
    {
        try {
            $company = Company::findOrFail($company_id);

            $companyStatesIds = CompanyServiceState::where('company_id', $company_id)
                ->distinct('state_id')
                ->pluck('state_id')
                ->unique()
                ->values();

            $companyZipcodes = CompanyServiceZip::where('company_id', $company_id)
                ->get(['zipcode_id', 'region_text', 'service_id', 'state_iso', 'company_id'])
                ->unique('zipcode_id')
                ->values();

            $service = Service::find($newServiceId);

            // Copy states — skip those already attached
            $existingStateIds = $service
                ? $service->states()
                    ->wherePivot('company_id', $company_id)
                    ->pluck('states.id')
                    ->all()
                : [];

            $companyStatesIds->each(function ($stateId) use ($service, $company_id, $existingStateIds) {
                if ($service && !in_array($stateId, $existingStateIds)) {
                    $service->states()->attach([
                        $stateId => ['company_id' => $company_id]
                    ]);
                }
            });

            // Copy zipcodes — bulk upsert, executed once (not per state)
            if ($service && $companyZipcodes->isNotEmpty()) {
                $existingZipcodeIds = CompanyServiceZip::where('service_id', $newServiceId)
                    ->where('company_id', $company_id)
                    ->pluck('zipcode_id')
                    ->all();

                $rows = $companyZipcodes
                    ->filter(fn($z) => !in_array($z->zipcode_id, $existingZipcodeIds))
                    ->map(fn($z) => [
                        'zipcode_id'  => $z->zipcode_id,
                        'company_id'  => $company_id,
                        'service_id'  => $newServiceId,
                        'region_text' => $z->region_text,
                        'active'      => true,
                        'state_iso'   => $z->state_iso,
                    ])
                    ->values()
                    ->all();

                foreach (array_chunk($rows, 500) as $chunk) {
                    CompanyServiceZip::insert($chunk);
                }
            }

            return $companyStatesIds;
        } catch (\Exception $e) {
            Log::error('copyStatesByService error', [
                'company_id' => $company_id,
                'service_id' => $newServiceId,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function adminRemoveService(Request $request)
    {

        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
        ]);
        //TODO policy
        $company = Company::find($request->company_id);
        $service = Service::find($request->service_id);
        $company->services()->detach($request->service_id);
        CompanyServiceState::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->delete();

        CompanyServiceZip::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->delete();
        return new UserCompanyResource($company);
    }

    public function update(Service $service, Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $service->name = $request->name;

        if ($request->filled('category_id')) {
            $service->category_id = $request->category_id;
        }
        if ($request->filled('price')) {
            $service->price = $request->price;
        }

        if ($request->filled('description')) {
            $service->description = $request->description;
        }

        $service->save();

        return ServiceResource::collection(Service::all());
    }

    public function storePrice(Request $request)
    {
        $request->validate([
            'service_id' => 'required',
            'price' => 'required',
        ]);

        $service = Service::findOrfail($request->service_id);
        $service->price = $request->price;
        $service->save();

        return ServiceResource::collection(Service::all());
    }
    public function destroy(Service $service)
    {
        $service->delete();
        return DashboardServiceResource::collection(Service::all());
    }
    public function show(Service $service)
    {
        $service = new DashboardServiceResource($service);
        return $service;
    }
    public function showPublic(Service $service)
    {
        $service = new ServicePublicResource($service);
        return $service;
    }

    public function zipcodesByRegion(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);
        $company = Company::findOrfail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->get();
        $serviceZipCodes = CompanyServiceZip::where('service_id', $request->service_id)
            ->where('company_id', $request->company_id)->where('state_iso', $request->state_iso)
            ->get();
        $serviceZipCodes = $serviceZipCodes->map(function ($zipcodes) {
            return $zipcodes->zipcode_id;
        });

        $zipcodes = $zipcodes->map(function ($zip) use ($serviceZipCodes) {
            if (in_array($zip->id, $serviceZipCodes->toArray())) {
                $zip->active = true;
            } else {
                $zip->active = false;
            }

            return $zip;
        });

        $zipcodesGroupByLocation = $zipcodes->groupBy('location');

        return $zipcodesGroupByLocation->map(function ($zipcodes) {
            #total zipcodes active atributte
            $zipcodesActive = $zipcodes->filter(function ($zip) {
                return $zip->active;
            });
            return [
                'total_locations' => $zipcodes->count(),
                'total_active_locations' => $zipcodesActive->count(),
                'zipcodes' => $zipcodes

            ];
        });
    }
    public function adminZipcodesByRegion(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);

        $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->get();
        $serviceZipCodes = CompanyServiceZip::where('service_id', $request->service_id)
            ->where('company_id', $request->company_id)->where('state_iso', $request->state_iso)
            ->get();
        $serviceZipCodes = $serviceZipCodes->map(function ($zipcodes) {
            return $zipcodes->zipcode_id;
        });

        $zipcodes = $zipcodes->map(function ($zip) use ($serviceZipCodes) {
            if (in_array($zip->id, $serviceZipCodes->toArray())) {
                $zip->active = true;
            } else {
                $zip->active = false;
            }

            return $zip;
        });

        $zipcodesGroupByLocation = $zipcodes->groupBy('location');

        return $zipcodesGroupByLocation->map(function ($zipcodes) {
            #total zipcodes active atributte
            $zipcodesActive = $zipcodes->filter(function ($zip) {
                return $zip->active;
            });
            return [
                'total_locations' => $zipcodes->count(),
                'total_active_locations' => $zipcodesActive->count(),
                'zipcodes' => $zipcodes

            ];
        });
    }
    public function zipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);
        //Necesita politica
        $service = Service::findOrfail($request->service_id);
        $company = Company::findOrfail($request->company_id);

        $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->get();

        $zipcodes_return = array_map(function ($zip) use ($service, $request, $company) {
            $service->zipcodes()->syncWithoutDetaching([
                $zip['id'] => ['company_id' => $company->id, 'region_text' => $request->region, 'active' => true, 'state_iso' => $zip['state_iso']]
            ]);
        }, $zipcodes->toArray());
        return 'Ok';
    }
    public function deleteZipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);
        //Necesita politica

        $company = Company::findOrfail($request->company_id);
        $service = $company->services()->where('company_id', $request->company_id)->first();
        // $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->where('company_id', $request->company_id)->get();


        $zipcodes =  $service->zipcodes()
            ->newPivotStatement()
            ->where('service_id', $service->id)  // Asegúrate de incluir la clave foránea del modelo principal
            ->where('region_text', $request->region)
            ->where('state_iso', $request->state_iso)
            ->delete();

        return $zipcodes;
    }

    public function updateZipcodes(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'region' => 'required',
        ]);

        $service = Service::find($request->service_id);
        if (isset($request->zipcodes)) {

            $service->companyServiceZip()
                ->where('region_text', $request->region)
                ->where('company_id', $request->company_id)
                ->where('service_id', $request->service_id)
                ->delete();



            return array_map(function ($zip) use ($service, $request) {
                $service->companyServiceZip()->create(['zipcode_id' => $zip['id'], 'company_id' => $request->company_id, 'region_text' => $request->region, 'active' => true, 'state_iso' => $zip['state_iso']]);
                return $zip['zipcode'];
            }, $request->zipcodes);
        }
    }
    public function addZipcode(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'region' => 'required',
            'zipcodes' => 'required',
        ]);

        $company = Company::findOrfail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $service = Service::find($request->service_id);
        if (isset($request->zipcodes)) {
            return array_map(function ($zip) use ($service, $request) {
                $service->companyServiceZip()->create(['zipcode_id' => $zip['id'], 'company_id' => $request->company_id, 'region_text' => $request->region, 'active' => true, 'state_iso' => $zip['state_iso']]);
                return $zip['zipcode'];
            }, $request->zipcodes);
        }
        return  'ok';
    }

    public function adminServiceAddZipcode(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'region' => 'required',
            'zipcodes' => 'required',
        ]);

        $service = Service::find($request->service_id);
        if (isset($request->zipcodes)) {
            return array_map(function ($zip) use ($service, $request) {
                $service->companyServiceZip()->create(['zipcode_id' => $zip['id'], 'company_id' => $request->company_id, 'region_text' => $request->region, 'active' => true, 'state_iso' => $zip['state_iso']]);
                return $zip['zipcode'];
            }, $request->zipcodes);
        }
        return  'ok';
    }
    public function addZipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);

        $service = Service::findOrfail($request->service_id);
        $company = Company::findOrfail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->get();
        foreach ($zipcodes as $key => $zip) {
            # code...
            $exists = CompanyServiceZip::where('company_id', $company->id)->where('service_id', $service->id)->where('region_text', $request->region)->where('zipcode_id', $zip->id)->exists();

            if (!$exists) {
                CompanyServiceZip::create([
                    'company_id' => $company->id,
                    'region_text' => $zip->region,
                    'state_iso' => $zip->state_iso,
                    'service_id' => $service->id,
                    'zipcode_id' => $zip->id,
                    'active' => 1
                ]);
            }
        }
        return  'ok';
    }
    public function adminServiceAddZipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);
        //Necesita politica
        $service = Service::findOrfail($request->service_id);
        $company = Company::findOrfail($request->company_id);

        $zipcodes = Zipcode::where('region', $request->region)->where('state_iso', $request->state_iso)->get();
        foreach ($zipcodes as $key => $zip) {
            # code...
            $exists = CompanyServiceZip::where('company_id', $company->id)->where('service_id', $service->id)->where('region_text', $request->region)->where('zipcode_id', $zip->id)->exists();
            if (!$exists) {
                CompanyServiceZip::create([
                    'company_id' => $company->id,
                    'region_text' => $zip->region,
                    'state_iso' => $zip->state_iso,
                    'service_id' => $service->id,
                    'zipcode_id' => $zip->id,
                    'active' => 1
                ]);
            }
        }
        return  'ok';
    }
    public function removeZipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);

        $service = Service::findOrfail($request->service_id);
        $company = Company::findOrfail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $zipcodes = CompanyServiceZip::where('service_id', $service->id)->where('region_text', $request->region)->where('state_iso', $request->state_iso)->where('company_id', $request->company_id)->delete();
        return  'Deleted success';
    }
    public function adminServiceRemoveZipcodesByCounty(Request $request)
    {
        $request->validate([
            'region' => 'required',
            'state_iso' => 'required',
            'service_id' => 'required',
            'company_id' => 'required'
        ]);

        $service = Service::findOrfail($request->service_id);
        $company = Company::findOrfail($request->company_id);
        $zipcodes = CompanyServiceZip::where('service_id', $service->id)->where('region_text', $request->region)->where('state_iso', $request->state_iso)->where('company_id', $request->company_id)->delete();
        return  'Deleted success';
    }
    public function removeZipcode(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'region' => 'required',
            'zipcodes' => 'required',
        ]);
        $company = Company::findOrfail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $service = Service::find($request->service_id);
        if (isset($request->zipcodes)) {


            return array_map(function ($zip) use ($service, $request) {
                $service->companyServiceZip()->where('region_text', $request->region)
                    ->where('company_id', $request->company_id)
                    ->where('company_id', $request->company_id)
                    ->where('zipcode_id', $zip['id'])
                    ->delete();
                return $zip['zipcode'];
            }, $request->zipcodes);
        }
        return 'eliminados';
    }
    public function adminServiceRemoveZipcode(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'region' => 'required',
            'zipcodes' => 'required',
        ]);

        $service = Service::find($request->service_id);
        if (isset($request->zipcodes)) {


            return array_map(function ($zip) use ($service, $request) {
                $service->companyServiceZip()->where('region_text', $request->region)
                    ->where('company_id', $request->company_id)
                    ->where('company_id', $request->company_id)
                    ->where('zipcode_id', $zip['id'])
                    ->delete();
                return $zip['zipcode'];
            }, $request->zipcodes);
        }
        return 'eliminados';
    }

    public function selectAllState(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'state_id' => 'required',
        ]);

        $service = Service::find($request->service_id);
        $company = Company::findOrFail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $state = State::find($request->state_id);
        $zipcodes = Zipcode::where('state_iso', $state->iso_code)->get();
        $service->companyServiceZip()->where('company_id', $request->company_id)->where('state_iso', $state->iso_code)->delete();
        foreach ($zipcodes as $key => $zip) {
            # code...
            $service->companyServiceZip()->create(
                ['zipcode_id' => $zip->id, 'company_id' => $request->company_id, 'region_text' => $zip->region, 'active' => true, 'state_iso' => $state->iso_code]
            );
        }

        return response()->json([
            'Updated successfully'
        ]);
    }
    public function adminServiceSelectAllState(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'state_id' => 'required',
        ]);

        $service = Service::find($request->service_id);
        $state = State::find($request->state_id);
        $zipcodes = Zipcode::where('state_iso', $state->iso_code)->get();
        $service->companyServiceZip()->where('company_id', $request->company_id)->where('state_iso', $state->iso_code)->delete();
        foreach ($zipcodes as $key => $zip) {
            # code...
            $service->companyServiceZip()->create(
                ['zipcode_id' => $zip->id, 'company_id' => $request->company_id, 'region_text' => $zip->region, 'active' => true, 'state_iso' => $state->iso_code]
            );
        }

        return response()->json([
            'Updated successfully'
        ]);
    }
    public function removeAllState(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'state_id' => 'required',
        ]);

        $service = Service::find($request->service_id);
        $state = State::find($request->state_id);
        $company = Company::findOrFail($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $zipcodes = Zipcode::where('state_iso', $state->iso_code)->get();
        dd($state->iso_code);
        $service->companyServiceZip()->where('company_id', $request->company_id)->where('state_iso', $state->iso_code)->delete();

        return response()->json([
            'Updated successfully'
        ]);
    }
    public function adminRemoveAllState(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
            'state_id' => 'required',
        ]);

        $service = Service::find($request->service_id);

        $state = State::find($request->state_id);
        Zipcode::where('state_iso', $state->iso_code)->get();
        $service->companyServiceZip()->where('company_id', $request->company_id)->where('state_iso', $state->iso_code)->delete();

        return response()->json([
            'Updated successfully'
        ]);
    }
    public function pause(Request $request)
    {

        $request->validate([
            'service_id' => 'required',
            'company_id' => 'required',
            'pause' => 'required',
        ]);

        $company = Company::find($request->company_id);
        $service = $company->services->where('id', $request->service_id)->first();
        $user = auth()->user();

        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $serviceCompany = CompanyService::where('company_id', $company->id)->where('service_id', $service->id)->first();
        $serviceCompany->pause = $request->pause;
        $serviceCompany->save();

        return $serviceCompany;
    }

    public function storeImage(Service $service, Request $request)
    {
        $request->validate([
            'image' => 'required'
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $uuid = uniqid();
            $nombreArchivo = $service->id . '/image-' . $uuid . '.' . $image->extension();
            $rutaAbsoluta = storage_path('app/public/services/' . $nombreArchivo);
            // Guardar la imagen original en el disco
            Storage::disk('services')->put($nombreArchivo, file_get_contents($image));
            // Optimizar la imagen usando el comando Artisan image:convert
            Artisan::call('image:convert', ['image' => $rutaAbsoluta]);
            // Obtener la URL optimizada
            $urlArchivo = Storage::disk('services')->url($nombreArchivo);
            $nombreArchivo2 = $service->id . '/image-' . $uuid . '2.' . $image->extension();

            $urlArchivo2 = Storage::disk('services')->url($nombreArchivo2);
            $service->image = $urlArchivo2;
            $service->save();
        }

        $service->save();

        return $service;
    }

    public function adminGetService($slug, $company_id)
    {
        $service = Service::where('slug', $slug)->firstOrFail();
        $states = $service->states()->where('company_id', $company_id)->get();

        $stateIsoCodes = $states->pluck('iso_code')->filter()->all();

        // 1 query: all configured zipcodes for this service+company, eager-load zipcode relation
        $allServiceZips = CompanyServiceZip::where('service_id', $service->id)
            ->where('company_id', $company_id)
            ->with('zipcode')
            ->get()
            ->groupBy('state_iso');

        // 1 query: zipcode counts per (state_iso, region)
        $regionTotals = Zipcode::selectRaw('state_iso, region, COUNT(*) as total')
            ->whereIn('state_iso', $stateIsoCodes)
            ->groupBy('state_iso', 'region')
            ->get()
            ->groupBy('state_iso');

        // 1 query: total zipcodes per state_iso
        $stateTotals = Zipcode::selectRaw('state_iso, COUNT(*) as total')
            ->whereIn('state_iso', $stateIsoCodes)
            ->groupBy('state_iso')
            ->pluck('total', 'state_iso');

        $states->map(function ($state) use ($allServiceZips, $regionTotals, $stateTotals) {
            $stateZips = $allServiceZips->get($state->iso_code, collect());
            $stateRegionTotals = $regionTotals->get($state->iso_code, collect());

            $state->regions = $state->regions()->map(function ($region) use ($state, $stateZips, $stateRegionTotals) {
                $serviceZipCodes = $stateZips
                    ->filter(fn($z) => $z->region_text == $region)
                    ->map(fn($z) => $z->zipcode)
                    ->values();

                $regionRow = $stateRegionTotals->first(fn($r) => $r->region == $region);
                $zipcodesByRegion = $regionRow ? (int) $regionRow->total : 0;
                $allSelected = $zipcodesByRegion <= count($serviceZipCodes);

                return [
                    "state_iso"        => $state->iso_code,
                    "name"             => $region["name"],
                    "zipcodes"         => $serviceZipCodes,
                    "zipTotal"         => count($serviceZipCodes),
                    "zipcodesByRegion" => $zipcodesByRegion,
                    "allSelected"      => $allSelected,
                ];
            });

            $state->totalSelected = collect($state->regions)->reduce(fn($sum, $r) => $sum + count($r['zipcodes']), 0);
            $state->totalZipcodes = (int) $stateTotals->get($state->iso_code, 0);
            $state->allSelected   = $state->totalSelected >= $state->totalZipcodes;

            return $state;
        });

        $service->states = $states->sortBy('name_en');

        return new CompanyServiceResource($service);
    }
}
