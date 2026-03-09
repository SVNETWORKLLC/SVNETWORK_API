<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'answer_id' => $this->id ?? null,
            'question_text' => $this->question?->text,
            'answer_text' => $this->text ?? 'N/A'
        ];
    }
}
