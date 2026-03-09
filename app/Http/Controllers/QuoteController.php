<?php

namespace App\Http\Controllers;

use App\Http\Resources\QuoteResource;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Models\Zipcode;
use App\Notifications\RequestAQuoteNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuoteController extends Controller
{
    public function show(Quote $quote)
    {
        $user = auth()->user();

        $userCompaniesIds = $user->companies->pluck('id');

        $match = $quote->company()->whereIn('id', $userCompaniesIds)->exists();

        if (!$match) {
            abort(403);
        }
        return new QuoteResource($quote);
    }
    public function store(Request $request)
    {
        $request->validate([
            'data' => 'required'
        ]);
        $files = $request->file('images');
        $rawData = $request->input('data');
        $payload = is_string($rawData)
            ? json_decode($rawData)
            : json_decode(json_encode($rawData ?? []));

        $user = User::where('email', $payload->email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $payload->name ?? null,
                'surname' => $payload->lastname ?? null,
                'email' => $payload->email ?? null,
                'phone' => $payload->phone ?? null,
            ]);
        }

        $service = Service::find($payload->service_id);
        $company = Company::find($payload->company_id);
        $zipcode = Zipcode::where('zipcode', $payload->zipcode)->first();
        $title = $service->name . ' in ' . $zipcode->location . ', ' . $zipcode->state . ' ' . $zipcode->zipcode;

        $quote = $user->quotes()->create([
            'description' => $payload->details,
            'service_id' => $service->id,
            'title' => $title,
            'zipcode_id' => $zipcode->id,
            'user_id' => $user->id,
            'state_iso' => $zipcode->state_iso,
            'company_id' => $company->id
        ]);
        $quote->link = config('app.app_url') . '/user/companies/profile/quotes/' . $quote->id . '?utm_source=email';
        $data = [
            'user' => $user,
            'quote' => new QuoteResource($quote)
        ];

        if ($request->hasFile('images')) {
            foreach ($files as $image) {
                $filename = $quote->uuid.'/image-'.uniqid().'.'.$image->extension();
                Storage::disk('quotes')->put($filename, file_get_contents($image));
                $extension = $image->extension();
                $size = $image->getSize();
                $mimetype = $image->getMimeType();

                $image = $quote->images()->create([
                    'filename' => $filename,
                    'mime_type' => $mimetype,
                    'extension' => $extension,
                    'type' => 1,
                    'size' => $size,
                ]);
            }
        }
        //Enviar notificaciones a la compania
        if ($company->users->count() > 0) {
            $company->users[0]->notify(new RequestAQuoteNotification($data));
        }
        //Enviar notificacion al usuario
        return $quote;
    }
    public function send(Request $request)
    {
        $request->validate([
            'quote_id' => 'required',
        ]);

        $quote = Quote::find($request->quote_id);

        if ($quote->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $company = $quote->company;
        $quote->link = config('app.app_url') . '/user/companies/profile/quotes/' . $quote->id;
        $data = [
            'user' => auth()->user(),
            'quote' => new QuoteResource($quote)
        ];
        //Enviar notificaciones a la compania
        if ($company->users->count() > 0) {
            $company->users[0]->notify(new RequestAQuoteNotification($data));
        }

        //Enviar notificacion al usuario
        return $quote;
    }

    public function acceptQuote(Request $request)
    {
        $request->validate([
            'quote_id' => 'required',
            'user_id' => 'required'
        ]);

        $quote = Quote::find($request->quote_id);
        $quote->acepted = 1;
        $quote->save();

        return response()->json(['message' => 'Quote accepted successfully']);
    }

    public function storeImage(Request $request)
    {
        $request->validate([
            'quote_id' => 'required',
            'images' => 'required'
        ]);

        $quote = Quote::find($request->quote_id);
        $images = $request->file('images');

        foreach ($images as $key => $image) {

            //Save image
            if (!$image || !$image->isValid()) {
                return response()->json(['error' => 'Invalid image upload'], 422);
            }

            if (!in_array($image->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return response()->json(['error' => 'Unsupported file type'], 422);
            }



            $filename = $quote->uuid . '/image-' . uniqid() . '.' . $image->extension();
            Storage::disk('quotes')->put($filename, file_get_contents($image));
            $extension = $image->extension();
            $size = $image->getSize();
            $mimetype = $image->getMimeType();

            $image = $quote->images()->create([
                'filename' => $filename,
                'mime_type' => $mimetype,
                'extension' => $extension,
                'type' => 1,
                'size' => $size
            ]);


        }
        return response()->json(['message' => 'Images uploaded successfully', 'images' => $quote->images]);
    }
}
