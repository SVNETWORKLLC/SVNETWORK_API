<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceNewAddedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service_name' =>$this->service->name ?? 'No service',
            'service_slug' =>$this->service->slug ?? 'no-service',
            'state_name'=> $this->state->name_en ?? 'No state',
            'state_slug'=> $this->state->slug ?? 'no-state'
        ];
    }
}
