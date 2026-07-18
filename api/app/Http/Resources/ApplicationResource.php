<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'candidate_name' => $this->candidate_name,
            'candidate_email' => $this->candidate_email,
            'source' => $this->source,
            'external_reference' => $this->external_reference,
            'cv_original_name' => $this->cv_original_name,
            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'scope_score' => $this->scope_score,
            'scope_reason' => $this->scope_reason,
            'final_score' => $this->final_score,
            'score_breakdown' => $this->score_breakdown,
            'ai_summary' => $this->ai_summary,
            'candidate_feedback' => $this->candidate_feedback,
            'recruiter_rank' => $this->recruiter_rank,
            'read_at' => $this->read_at?->toIso8601String(),
            'selected_at' => $this->selected_at?->toIso8601String(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'annotations' => $this->whenLoaded('annotations', fn () => $this->annotations->map(fn ($annotation) => [
                'uuid' => $annotation->public_id,
                'body' => $annotation->body,
                'created_at' => $annotation->created_at?->toIso8601String(),
            ])),
        ];
    }
}
