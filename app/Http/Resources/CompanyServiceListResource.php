<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyServiceListResource extends JsonResource
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
            "name" => $this->name,
            "slug" => $this->slug,
            "description" => $this->description,
            "pause" => $this->pause,
            "price" => $this->price,
            "category_id" => $this->category_id,

            "states" => StateResource::collection($this->states),
            "pause" => $this->pivot->pause == 1 ? true :false,
            "active" => $this->pivot->pause == 0 ? true : false
        ];
    }
}
