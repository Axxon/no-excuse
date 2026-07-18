<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'description' => $this->description,
            'criteria' => $this->criteria,
            'closes_at' => $this->closes_at?->toIso8601String(),
        ];
    }
}
