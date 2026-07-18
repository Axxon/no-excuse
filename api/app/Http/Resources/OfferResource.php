<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
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
            'rejection_message' => $this->rejection_message,
            'final_rejection_message' => $this->final_rejection_message,
            'screening_provider' => $this->screening_provider,
            'screening_model' => $this->screening_model,
            'scoring_provider' => $this->scoring_provider,
            'scoring_model' => $this->scoring_model,
            'status' => $this->status,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'intake_url' => url('/api/v1/intake/'.$this->public_id.'/applications'),
            'applications_count' => $this->whenCounted('applications'),
            'pending_count' => $this->when(isset($this->pending_count), (int) ($this->pending_count ?? 0)),
            'shortlisted_count' => $this->when(isset($this->shortlisted_count), (int) ($this->shortlisted_count ?? 0)),
        ];
    }
}
