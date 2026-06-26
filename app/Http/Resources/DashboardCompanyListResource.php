<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCompanyListResource extends JsonResource
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

            "email" => $this->email,

            "state" => $this->state,

            "matches" => $this->matches,

            "verified" => $this->verified,

            "services" => CompanyServiceListResource::collection($this->services),
            "format_date" => $this->created_at->format('m/d/Y h:i A'),
            "updated_at" => $this->updated_at,

        ];
    }
}
