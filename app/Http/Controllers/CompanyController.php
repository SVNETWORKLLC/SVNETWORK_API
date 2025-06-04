<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyBasicResource;
use App\Http\Resources\CompanyConfigurationResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\CompanyServiceResource;
use App\Http\Resources\DashboardCompanyResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\UserCompanyResource;
use App\Jobs\ConvertImageJob;
use App\Jobs\ConvertVideoJob;
use App\Models\Company;
use App\Models\CompanyServiceState;
use App\Models\CompanyServiceZip;
use App\Models\Image;
use App\Models\Mautic;
use App\Models\Service;
use App\Models\State;
use App\Models\User;
use App\Models\Zipcode;
use App\Notifications\CompanyCreatedNotification;
use App\Notifications\CompanyVerifiedNotification;
use App\Notifications\LicenceVerificationNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{

    public function list()
    {
        $companies = Company::orderBy('updated_at', 'desc')->get();
        return DashboardCompanyResource::collection($companies);
    }

    public function getProgress()
    {
        $user = auth()->user();
        $company = $user->companies->first();
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $servicesStates = $company->services()->with('states')->get();
        $servicesWithoutStates = $servicesStates->filter(function ($service) {
            return $service->states->count() == 0;
        })->values();
        $servicesIdsWithZipcodes = $company->companyServiceZip->groupBy('service_id')->keys();
        $servicesIds = $company->services->pluck('id');
        $servicesWithoutZip = $company->services()->wherePivotNotIn('service_id', $servicesIdsWithZipcodes)->get();

        return [
            'hasZipcodes' => $servicesIdsWithZipcodes->count() > 0,
            'services' => $servicesIds,
            'servicesWithoutZip' =>  ServiceResource::collection($servicesWithoutZip),
            'someServicesWithoutStates' =>  $servicesWithoutStates->count() > 0,
            'someServiceWithoutZip' => $servicesWithoutZip->count() > 0,
            'servicesWithoutStates' => ServiceResource::collection($servicesWithoutStates)
        ];
        $data = [
            'companies' => $company->verified,
            'licences' => $company->licence,
            'insurances' => $company->insurance,
            'hasServices' => $company->services->count() > 0,
            'zipcodes' => $zipcodes
        ];
        return $data;
    }
    public function listUnverified()
    {
        $companies = Company::where('verified', 0)->get();
        return CompanyResource::collection($companies);
    }

    public function show(Company $company)
    {
        return new DashboardCompanyResource($company);
    }
    public function projects(Company $company)
    {
        $project = $company->projects()->paginate();
        return $project;
    }
    public function verify(Request $request)
    {
        $request->validate([
            'company_id' => 'required'
        ]);

        $company = Company::findOrFail($request->company_id);
        $company->verified = 1;
        $company->save();
        $users = $company->users;
        if ($users) {
            foreach ($users as $key => $user) {
                $user->link = config('app.app_url') . '/user/companies/profile';
                $user->link2 = config('app.app_url') . '/legal/pro-terms';
                $user->notify(new CompanyVerifiedNotification($user));
            }
        }
        return new CompanyResource($company);
    }
    public function verifyLicence(Request $request)
    {
        $request->validate([
            'company_id' => 'required'
        ]);

        $company = Company::findOrFail($request->company_id);
        $company->licence = 1;
        $company->save();
        // $users = $company->users;
        // if ($users) {
        //     foreach ($users as $key => $user) {
        //         $user->link = config('app.app_url') . '/user/companies/profile';
        //         $user->link2 = config('app.app_url') . '/legal/pro-terms';
        //         $user->notify(new CompanyVerifiedNotification($user));
        //     }
        // }
        return new CompanyResource($company);
    }
    public function verifyInsurance(Request $request)
    {
        $request->validate([
            'company_id' => 'required'
        ]);

        $company = Company::findOrFail($request->company_id);
        $company->insurance = 1;
        $company->save();
        // $users = $company->users;
        // if ($users) {
        //     foreach ($users as $key => $user) {
        //         $user->link = config('app.app_url') . '/user/companies/profile';
        //         $user->link2 = config('app.app_url') . '/legal/pro-terms';
        //         $user->notify(new CompanyVerifiedNotification($user));
        //     }
        // }
        return new CompanyResource($company);
    }
    public function addUser(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'user_id' => 'required',
        ]);
        $user = User::find($request->user_id);
        $user->pro = true;
        $company = Company::findOrFail($request->company_id);
        $company->users()->syncWithoutDetaching($request->user_id);
        $user->save();
        return new CompanyResource($company);
    }
    public function showbySlug($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        return new CompanyResource($company);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'description' => 'required',
            'phone' => 'required',
            'address_line1' => 'required',
            'city' => 'required',
            'zip_code' => 'required',
            'state' => 'required'
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'

        $company = Company::create([
            'name' => $request->name,
            'email' => $request->email,
            'description' => $request->description,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'city' => $request->city,
            'state' => $request->state_id,
            'zip_code' => $request->zip_code
        ]);


        if ($request->filled('state')) {
            $company->state_id = $request->state["id"];
        }

        if ($request->filled('services')) {
            foreach ($request->services as $key => $service) {
                $company->services()->syncWithoutDetaching($service["id"]);
            }
        }
        if ($request->filled('categories')) {
            foreach ($request->categories as $key => $category) {
                $company->categories()->syncWithoutDetaching($category["id"]);
            }
        }
        if ($request->filled('phone_2')) {
            $company->phone_2 = $request->phone_2;
        }
        if ($request->filled('phone')) {
            $company->phone = $request->phone;
        }

        if ($request->filled('address_line2')) {
            $company->address_line2 = $request->address_line2;
        }

        if ($request->filled('social_facebook')) {
            $company->social_facebook = $request->social_facebook;
        }

        if ($request->filled('social_x')) {
            $company->social_x = $request->social_x;
        }

        if ($request->filled('social_youtube')) {
            $company->social_youtube = $request->social_youtube;
        }

        if ($request->filled('video_url')) {
            $company->video_url = $request->video_url;
        }

        $company->save();

        return new CompanyResource($company);
    }
    public function storeFromRegister(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'company_name' => 'required',
            'description' => 'required',
            'phone' => 'required',
            'address_line1' => 'required',
            'city' => 'required',
            'zip_code' => 'required',
            'state' => 'required',
            'user_id' => 'required',
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'
        $user = User::findOrfail($request->user_id);

        $company = Company::create([
            'name' => $request->company_name,
            'email' => $user->email,
            'description' => $request->description,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'city' => $request->city,
            'zip_code' => $request->zip_code
        ]);

        try {
            $data = [
                'firstname' => $user->name,
                'lastname' => $user->surname,
                'email' => $user->email,
                'company' => $request->company_name,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'state' => $request->state['name_en'],
                'zipcode' => $request->zip_code,
                'tags' => 'company'
            ];
            Mautic::createContact($data);
        } catch (Exception $e) {
            return $e;
        }
        if ($request->filled('state')) {
            $company->state_id = $request->state["id"];
        }

        if ($request->filled('services')) {
            foreach ($request->services as $key => $service) {
                $company->services()->syncWithoutDetaching($service["id"]);
            }
        }
        if ($request->filled('categories')) {
            foreach ($request->categories as $key => $category) {
                $company->categories()->syncWithoutDetaching($category["id"]);
            }
        }
        if ($request->filled('phone_2')) {
            $company->phone_2 = $request->phone_2;
        }
        if ($request->filled('phone')) {
            $company->phone = $request->phone;
        }

        if ($request->filled('address_line2')) {
            $company->address_line2 = $request->address_line2;
        }

        if ($request->filled('social_facebook')) {
            $company->social_facebook = $request->social_facebook;
        }

        if ($request->filled('social_x')) {
            $company->social_x = $request->social_x;
        }

        if ($request->filled('social_youtube')) {
            $company->social_youtube = $request->social_youtube;
        }

        if ($request->filled('video_url')) {
            $company->video_url = $request->video_url;
        }

        $company->save();

        $user->companies()->syncWithoutDetaching($company->id);


        $admins = User::where('is_admin', 1)->get();
        $link = config('app.app_url') . '/admin/companies';
        $company->link = $link;
        foreach ($admins as $user) {
            $user->notify(new CompanyCreatedNotification($company));
        }


        return new CompanyResource($company);
    }

    public function storeState(Request $request)
    {
        $request->validate([
            'service_id' => 'required',
            'state_id' => 'required',
            'company_id' => 'required'
        ]);
        $company = Company::find($request->company_id);
        $this->authorize('update', $company);

        $service = Service::find($request->service_id);


        $user = auth()->user();

        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $existing = $service->states()
            ->wherePivot('state_id', $request->state_id)
            ->wherePivot('company_id', $request->company_id)
            ->exists();

        if (!$existing) {
            $service->states()->attach([
                $request->state_id => ["company_id" => $request->company_id]
            ]);
        }

        // $service->companyServiceState()->where('company_id', $request->company_id)->delete();
        // if ($request->filled('states')) {
        //     if (isset($request->states)) {
        //         foreach ($request->states as $key => $state) {
        //             $service->states()->attach([$state['id'] => ["company_id" => $request->company_id]]);
        //         }
        //     }
        // }


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

            $zipcodesCount = 0;


            $state->areasTotal = 0;
            // if($state->region->zipcodes){
            //     $state->region_count = 3;
            // }
            return $state;
        });

        $service->states = $states;

        return new CompanyServiceResource($service);
    }
    public function removeState(Request $request)
    {
        $request->validate([
            'service_id' => 'required',
            'state_id' => 'required',
            'company_id' => 'required'
        ]);
        $company = Company::find($request->company_id);
        $this->authorize('update', $company);

        $service = Service::find($request->service_id);


        $user = auth()->user();

        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        CompanyServiceState::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->where('state_id', $request->state_id)
            ->delete();
        $state = State::find($request->state_id);
        $zipcodes = CompanyServiceZip::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->where('state_iso', $state->iso_code)->delete();
        // $service->companyServiceState()->where('company_id', $request->company_id)->delete();
        // if ($request->filled('states')) {
        //     if (isset($request->states)) {
        //         foreach ($request->states as $key => $state) {
        //             $service->states()->attach([$state['id'] => ["company_id" => $request->company_id]]);
        //         }
        //     }
        // }


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

            $zipcodesCount = 0;
            $zipcodesCount = collect($state->regions)->reduce(function ($sum, $region) {
                return $sum + count($region['zipcodes']);
            }, 0);

            $state->areasTotal = $zipcodesCount;
            // if($state->region->zipcodes){
            //     $state->region_count = 3;
            // }
            return $state;
        });
        $service->states = $states;
        return new CompanyServiceResource($service);
    }
    public function adminRemoveState(Request $request)
    {
        $request->validate([
            'service_id' => 'required',
            'state_id' => 'required',
            'company_id' => 'required'
        ]);
        $company = Company::find($request->company_id);


        $service = Service::find($request->service_id);

        CompanyServiceState::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->where('state_id', $request->state_id)
            ->delete();
        $state = State::find($request->state_id);
        $zipcodes = CompanyServiceZip::where('service_id', $service->id)
            ->where('company_id', $request->company_id)
            ->where('state_iso', $state->iso_code)->delete();
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

            $zipcodesCount = 0;
            $zipcodesCount = collect($state->regions)->reduce(function ($sum, $region) {
                return $sum + count($region['zipcodes']);
            }, 0);

            $state->areasTotal = $zipcodesCount;
            return $state;
        });
        $service->states = $states;
        return new CompanyServiceResource($service);
    }

    public function storeLogoAdmin(Company $company, Request $request)
    {
        $request->validate([
            'image' => 'required'
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $nombreArchivo = $company->id . '/logo-' . uniqid() . '.' . $image->extension();
            $rutaAbsoluta = storage_path('app/public/companies/' . $nombreArchivo);
            Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
            \Artisan::call('image:convert', ['image' => $rutaAbsoluta,  'extension' => $image->extension()]);
            $urlArchivo = Storage::disk('companies')->url($nombreArchivo);
            $company->logo_url = $urlArchivo;
        }

        $company->save();

        return $company;
    }
    public function  storeDocument(Company $company, Request $request)
    {

        $this->authorize('update', $company);

        if ($request->hasFile('images')) {
            $image = $request->file('images');
            if ($image->isValid()) {
                // Realizar acciones con cada imagen, como guardarla en el servidor

                $nombreArchivo = $company->id . '/documents/image-' . uniqid() . '.' . $image->extension();
                Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
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
                    'type' => Image::TYPE_DOCUMENT,
                    'mime_type' => $mimetype,
                    'extension' => $extension,
                    'width' => $ancho,
                    'height' => $alto,
                    'size' => $size
                ]);

                $admins = User::where('is_admin', 1)->get();
                $link = config('app.app_url') . '/admin/companies/' . $company->id;
                $company->link = $link;
                foreach ($admins as $user) {
                    $user->notify(new LicenceVerificationNotification($company));
                }
                return "Imágenes subidas correctamente.";
            } else {
                return 'no valido';
            }
        } else {
            return "No se encontraron imágenes para subir.";
        }
    }
    public function  storeLicence(Company $company, Request $request)
    {

        $this->authorize('update', $company);

        if ($request->hasFile('images')) {
            $image = $request->file('images');
            if ($image->isValid()) {
                // Realizar acciones con cada imagen, como guardarla en el servidor

                $nombreArchivo = $company->id . '/documents/image-' . uniqid() . '.' . $image->extension();
                Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
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
                    'type' => Image::TYPE_LICENCE,
                    'mime_type' => $mimetype,
                    'extension' => $extension,
                    'width' => $ancho,
                    'height' => $alto,
                    'size' => $size
                ]);

                $admins = User::where('is_admin', 1)->get();
                $link = config('app.app_url') . '/admin/companies/' . $company->id;
                $company->link = $link;
                foreach ($admins as $user) {
                    $user->notify(new LicenceVerificationNotification($company));
                }
                return "Imágenes subidas correctamente.";
            } else {
                return 'no valido';
            }
        } else {
            return "No se encontraron imágenes para subir.";
        }
    }

    public function storeLogo(Company $company, Request $request)
    {
        $request->validate([
            'image' => 'required'
        ]);


        //Policy
        $this->authorize('update', $company);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $nombreArchivo = $company->id . '/logo-' . uniqid() . '.' . $image->extension();
            $rutaAbsoluta = storage_path('app/public/companies/' . $nombreArchivo);
            Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
            \Artisan::call('image:convert', ['image' => $rutaAbsoluta]);
            $urlArchivo = Storage::disk('companies')->url($nombreArchivo);
            $company->logo_url = $urlArchivo;
        }

        $company->save();

        return $company;
    }
    public function storeCover(Company $company, Request $request)
    {
        $request->validate([
            'image' => 'required'
        ]);

        $this->authorize('update', $company);

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $nombreArchivo = $company->id . '/cover-' . uniqid() . '.' . $image->extension();;
            Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
            $urlArchivo = Storage::disk('companies')->url($nombreArchivo);
            $company->cover_url = $urlArchivo;
        }

        $company->save();

        return $company;
    }

    public function storeImages(Company $company, Request $request)
    {
        $this->authorize('update', $company);

        if ($request->hasFile('images')) {
            $imagenes = $request->file('images');

            foreach ($imagenes as $key => $image) {

                if ($image->isValid()) {
                    // Realizar acciones con cada imagen, como guardarla en el servidor
                    $nombreArchivo = $company->id . '/projects/image-' . uniqid($key) . '.' . $image->extension();
                    $rutaAbsoluta = storage_path('app/public/companies/' . $nombreArchivo);
                    Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
                    \Artisan::call('image:convert', ['image' => $rutaAbsoluta]);
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
                        'mime_type' => $mimetype,
                        'extension' => $extension,
                        'type' => Image::TYPE_IMAGE,
                        'width' => $ancho,
                        'height' => $alto,
                        'size' => $size
                    ]);
                }
            }
            return "Image uploaded.";
        } else {
            return "No se encontraron imágenes para subir.";
        }
    }

    public function adminStoreImages(Company $company, Request $request)
    {

        if ($request->hasFile('images')) {
            $imagenes = $request->file('images');

            foreach ($imagenes as $key => $image) {

                if ($image->isValid()) {
                    // Realizar acciones con cada imagen, como guardarla en el servidor
                    $nombreArchivo = $company->id . '/projects/image-' . uniqid($key) . '.' . $image->extension();
                    $fullPath = Storage::disk('companies')->path($nombreArchivo);

                    //Procesado de imagen
                    ConvertImageJob::dispatch($fullPath);

                    Storage::disk('companies')->put($nombreArchivo, file_get_contents($image));
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
                        'mime_type' => $mimetype,
                        'extension' => $extension,
                        'type' => Image::TYPE_IMAGE,
                        'width' => $ancho,
                        'height' => $alto,
                        'size' => $size
                    ]);
                } else {
                }
            }

            return "Image uploaded.";
        } else {
            return "No se encontraron imágenes para subir.";
        }
    }

    public function deleteImage(Image $image)
    {

        $company = $image->imageable;
        $this->authorize('deleteImage', $company);

        $disk = Storage::disk('companies');
        $image->delete();
        // Verifica si el archivo existe
        if ($disk->exists($image->filename)) {
            // Elimina el archivo
            $disk->delete($image->filename);
            return response()->json(['message' => 'Image deleted successfully.'], 200);
        } else {
            return response()->json(['message' => 'Image not found.'], 404);
        }
    }
    public function adminDeleteImage(Image $image)
    {

        $company = $image->imageable;


        $disk = Storage::disk('companies');
        $image->delete();
        // Verifica si el archivo existe
        if ($disk->exists($image->filename)) {
            // Elimina el archivo
            $disk->delete($image->filename);
            return response()->json(['message' => 'Image deleted successfully.'], 200);
        } else {
            return response()->json(['message' => 'Image not found.'], 404);
        }
    }

    public function storeVideo(Company $company, Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:102400'
        ]);
        $video = $request->file('video');

        // Verificar si el archivo se ha cargado correctamente
        if ($video->isValid()) {
            // Generar un nombre único para el archivo de video
            $nombreArchivo = '/video-' . uniqid() . '.' . $video->getClientOriginalExtension();
            $path = $company->id . $nombreArchivo;
            $fullPath = Storage::disk('companies')->path($path);

            try {
                // Guardar el archivo de video en el disco 'companies'
                $video->storeAs($company->id, $nombreArchivo, 'companies');
                ConvertVideoJob::dispatch($fullPath);

                // Obtener la URL del archivo de video almacenado
                $urlArchivo = Storage::disk('companies')->url($path);

                // Actualizar la URL del video en el modelo $company
                $company->video_url = $urlArchivo;

                // Guardar los cambios en la base de datos
                $company->save();

                return response()->json(['message' => 'Video guardado correctamente', 'url' => $urlArchivo]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error al guardar el video', 'error' => $e->getMessage()], 500);
            }
        } else {
            return response()->json(['message' => 'El video no es válido'], 400);
        }
    }

    public function updateAdmin(Company $company, Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'description' => 'required',
            'phone' => 'required',
            'address_line1' => 'required',
            'city' => 'required',
            'zip_code' => 'required',
            'state' => 'required',
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'

        $company->name = $request->name;
        $company->email = $request->email;
        $company->description = $request->description;
        $company->phone = $request->phone;
        $company->address_line1 = $request->address_line1;
        $company->city = $request->city;
        $company->zip_code = $request->zip_code;
        $company->video_url = $request->video_url;

        if ($request->filled('states')) {
            $company->state_id = $request->state["id"];
        }

        if ($request->filled('services')) {
            foreach ($request->services as $key => $service) {
                $company->services()->syncWithoutDetaching($service["id"]);
            }
        }
        if ($request->filled('categories')) {
            foreach ($request->categories as $key => $category) {
                $company->categories()->syncWithoutDetaching($category["id"]);
            }
        }
        if ($request->filled('phone_2')) {
            $company->phone_2 = $request->phone_2;
        }
        if ($request->filled('phone')) {
            $company->phone = $request->phone;
        }

        if ($request->filled('address_line2')) {
            $company->address_line2 = $request->address_line2;
        }

        if ($request->filled('social_facebook')) {
            $company->social_facebook = $request->social_facebook;
        }

        if ($request->filled('social_x')) {
            $company->social_x = $request->social_x;
        }

        if ($request->filled('social_youtube')) {
            $company->social_youtube = $request->social_youtube;
        }
        if ($request->filled('video_url')) {
            $company->video_url = $request->video_url;
        }

        $company->save();
        $companies = Company::orderBy('id', 'desc')->get();
        return CompanyResource::collection($companies);
    }
    public function update(Company $company, Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'description' => 'required',
            'phone' => 'required',
            'address_line1' => 'required',
            'city' => 'required',
            'zip_code' => 'required',
            'state' => 'required',
        ]);
        // 'required|mimes:doc,docx,odt,pdf|max:2048'

        $company->name = $request->name;
        $company->email = $request->email;
        $company->description = $request->description;
        $company->phone = $request->phone;
        $company->address_line1 = $request->address_line1;
        $company->city = $request->city;
        $company->zip_code = $request->zip_code;
        $company->video_url = $request->video_url;

        if ($request->filled('states')) {
            $company->state_id = $request->state["id"];
        }

        if ($request->filled('services')) {
            foreach ($request->services as $key => $service) {
                $company->services()->syncWithoutDetaching($service["id"]);
            }
        }
        if ($request->filled('categories')) {
            foreach ($request->categories as $key => $category) {
                $company->categories()->syncWithoutDetaching($category["id"]);
            }
        }
        if ($request->filled('phone_2')) {
            $company->phone_2 = $request->phone_2;
        }
        if ($request->filled('phone')) {
            $company->phone = $request->phone;
        }

        if ($request->filled('address_line2')) {
            $company->address_line2 = $request->address_line2;
        }

        if ($request->filled('social_facebook')) {
            $company->social_facebook = $request->social_facebook;
        } else {
            $company->social_facebook = '';
        }

        if ($request->filled('social_x')) {
            $company->social_x = $request->social_x;
        } else {
            $company->social_x = '';
        }

        if ($request->filled('social_youtube')) {
            $company->social_youtube = $request->social_youtube;
        } else {
            $company->social_youtube = '';
        }

        if ($request->filled('video_url')) {
            $company->video_url = $request->video_url;
        } else {
            $company->video_url = '';
        }

        $company->save();
        $companies = Company::orderBy('id', 'desc')->get();
        return CompanyResource::collection($companies);
    }
    public function addService(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service' => 'required',
        ]);

        $user = auth()->user();
        $company = Company::find($request->company_id);
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $company = Company::find($request->company_id);
        $service = Service::find($request->service);
        $company->services()->syncWithoutDetaching([
            $request->service => [
                'pause' => 0
            ]
        ]);

        if (isset($request->copyStatesFromOthers) && $request->copyStatesFromOthers == true) {
            $firstService = $company->services->first();
            $repuesta = self::copyStatesByService($company->id, $request->service);
        }

        return new UserCompanyResource($company);
    }
    public function copyStates(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
        ]);

        $user = auth()->user();
        $company = Company::find($request->company_id);
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
        $service = Service::find($request->service_id);

        //Agrego todo los estados y zipcodes
        $repuesta = self::copyStatesByService($company->id, $request->service_id);
        return new UserCompanyResource($company);
    }
    public function adminServicesCopyStates(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
        ]);


        $company = Company::find($request->company_id);
        Service::find($request->service_id);
        //Agrego todo los estados y zipcodes
        $repuesta = self::copyStatesByService($company->id, $request->service_id);

        return new UserCompanyResource($company);
    }

    public function copyStatesByService($company_id, $newServiceId = null)
    {

        $companyStatesIds =  CompanyServiceState::where('company_id', $company_id)->select('state_id')->pluck('state_id')
            ->unique()
            ->values() // Reindexa el array para evitar claves desordenadas
            ->toArray();


        //1687
        // $companyZipcodes = CompanyServiceZip::where('company_id', $company_id)->get()->map(function ($zipcode) {
        //     return [
        //         'id' => $zipcode->zipcode_id,
        //         'region' => $zipcode->region_text,
        //         'service_id' => $zipcode->service_id,
        //         'state_iso' => $zipcode->state_iso,
        //         'company_id' => $zipcode->company_id,
        //     ];
        // })->unique()->values();

        $companyZipcodes = CompanyServiceZip::where('company_id', $company_id)
            ->select(
                'zipcode_id as id',
                'region_text as region',
                'service_id',
                'state_iso',
                'company_id'
            )->get();

        $service = Service::find($newServiceId);
        foreach ($companyStatesIds as $key => $state_id) {
            $exists = $service?->states()
                ->wherePivot('state_id', $state_id)
                ->wherePivot('company_id', $company_id)
                ->exists();

            if (!$exists) {
                $service->states()->attach([
                    $state_id => ["company_id" => $company_id]
                ]);
            }
        }

        foreach ($companyZipcodes as $zipcode) {
        $exists = $service->zipcodes()
            ->wherePivot('zipcode_id', $zipcode['id'])
            ->wherePivot('company_id', $company_id)
            ->exists();

        if (!$exists) {
            CompanyServiceZip::create([
                'zipcode_id' => $zipcode['id'],
                'company_id' => $company_id,
                'service_id' => $newServiceId,
                'region_text' => $zipcode['region'],
                'active' => true,
                'state_iso' => $zipcode['state_iso']
            ]);
        }
    }
        // $companyStatesIds->map(function ($stateId) use ($company_id, $newServiceId, $companyZipcodes) {

        //     $service = Service::find($newServiceId);

        //     $exists = $service?->states()
        //     ->wherePivot('state_id', $stateId)
        //     ->wherePivot('company_id', $company_id)
        //     ->exists();

        //     if (!$exists) {

        //         $service->states()->attach([
        //             $stateId => ["company_id" => $company_id]
        //         ]);
        //     }

        //     foreach ($companyZipcodes as $zipcode) {
        //         $exists = $service->zipcodes()
        //             ->wherePivot('zipcode_id', $zipcode['id'])
        //             ->wherePivot('company_id', $company_id)
        //             ->exists();

        //         if (!$exists) {
        //             CompanyServiceZip::create([
        //                 'zipcode_id' => $zipcode['id'],
        //                 'company_id' => $company_id,
        //                 'service_id' => $newServiceId,
        //                 'region_text' => $zipcode['region'],
        //                 'active' => true,
        //                 'state_iso' => $zipcode['state_iso']
        //             ]);
        //         }
        //     }
        // });

        // return $companyStatesIds;
    }
    public function addState(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'state_id' => 'required',
        ]);

        $company = Company::find($request->company_id);
        $service = Service::find($request->state_id);
        $company->services()->syncWithoutDetaching([
            $request->service => [
                'pause' => 0
            ]
        ]);

        return new UserCompanyResource($company);
    }
    public function detachService(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'service_id' => 'required',
        ]);
        //TODO policy
        $company = Company::find($request->company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }
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

    public function destroy(Company $company)
    {
        $company->destroy($company->id);
        return response('Company deleted', 201);
    }

    public function getConfiguration(Company $company)
    {

        return new CompanyConfigurationResource($company);
    }
    public function getService($slug, $company_id)
    {
        // Politica para poder ver los datos
        $company = Company::find($company_id);
        $user = auth()->user();
        if (!$user->companies->where('id', $company->id)->count()) {
            abort(403);
        }

        $service = Service::where('slug', $slug)->first();
        $states = $service->states()->where('company_id', $company_id)->get();
        $states->map(function ($state) use ($service, $company_id) {

            $state->regions = $state->regions()->map(function ($region) use ($service, $company_id, $state) {

                $serviceZipCodes = CompanyServiceZip::where('service_id', $service->id)
                    ->where('company_id', $company_id)->where('region_text', $region)->where('state_iso', $state->iso_code)
                    ->get();
                $serviceZipCodes = $serviceZipCodes->map(function ($zipcodes) {
                    return $zipcodes->zipcode;
                });

                $zipcodesByRegion = Zipcode::where('state_iso', $state->iso_code)->where('region', $region)->count();
                $allSelected = $zipcodesByRegion <= count($serviceZipCodes);
                return ["state_iso" => $state->iso_code, "name" => $region["name"], "zipcodes" => $serviceZipCodes, "zipTotal" => count($serviceZipCodes), "zipcodesByRegion" => $zipcodesByRegion, "allSelected" => $allSelected];
            });


            $zipcodesCount = 0;
            $zipcodesCount = collect($state->regions)->reduce(function ($sum, $region) {
                return $sum + count($region['zipcodes']);
            }, 0);

            $state->totalSelected = $zipcodesCount;
            // if($state->region->zipcodes){
            //     $state->region_count = 3;
            // }
            $state->totalZipcodes = $state->zipcodes->count();
            $state->allSelected =  $state->totalSelected >= $state->totalZipcodes;
            return $state;
        });
        $service->states = $states->sortBy('name_en');


        return new CompanyServiceResource($service);
    }
    public function newAddedCompanies()
    {
        //Companies que tienen servicios, que tienen logo y que son verificados
        $companies = Company::where('verified', 1)->whereHas('services')->whereNotNull('logo_url')->orderBy('id', 'desc')->take(20)->get();
        // $companies = Company::where('verified', 1)->orderBy('id', 'desc')->take(10)->get();

        return CompanyBasicResource::collection($companies);
    }

    public function companyWelcomeNotification(Company $company) {}
}
