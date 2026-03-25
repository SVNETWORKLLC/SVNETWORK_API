<?php

namespace App\Http\Resources;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "uuid" => $this->uuid,
            "name" => $this->name ,
            "slug" => $this->slug,
            "description" => $this->description,
            "email" => $this->email,
            "city" => $this->city,
            "zip_code" => $this->zip_code ,
            "address_line1" => $this->address_line1 ,
            "address_line2" => $this->address_line2 ,
            "state_id" => $this->state_id ,
            "state" => $this->state,
            "phone" => $this->phone,
            "phone_2" => $this->phone_2,
            "social_facebook" => $this->social_facebook,
            "social_x" => $this->social_x,
            "social_youtube" => $this->social_youtube,
            "web" => $this->web,
            "video_url" => $this->video_url,
            "verified" => $this->verified,
            "country_id" => $this->country_id,
            "images" => ImageCompanyResource::collection($this->images->where('type',Image::TYPE_IMAGE)->sortByDesc('updated_at')),
            "licence" => new ImageCompanyResource($this->images->where('type',Image::TYPE_LICENCE)->first()),
            "documents" =>  ImageCompanyResource::collection($this->images->where('type',Image::TYPE_DOCUMENT)),
            "logo_url" => $this->logo_url,
            "cover_url" => $this->cover_url,
            "reviews" => ReviewResource::collection($this->reviews),
            "services" =>  CompanyServiceListResource::collection($this->services ?? []),
            'projects' => CompanyProjectsResource::collection($this->projects ?? []),
            'leads' => LeadResource::collection($this->leads ?? []),
            'quotes' => QuoteResource::collection($this->quotes ?? []),
            'date' => $this->created_at->format('m/d/Y h:i A'),
        ];
    }
}
