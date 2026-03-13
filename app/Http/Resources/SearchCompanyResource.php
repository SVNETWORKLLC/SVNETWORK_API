<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
                'company_name' => $this->name,
                'company_description' => $this->description,
                'company_slug' => $this->slug,
                'reviews' => $this->reviews?->count() ?? 0,
                'rating' => $this->review_rate,
                'company_city' => $this->city,
                'company_logo' => $this->logo_url,
                'verified' => $this->verified,
            ];
    }
}
