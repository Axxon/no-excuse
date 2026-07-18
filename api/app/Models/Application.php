<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_name', 'candidate_email', 'cv_path', 'cv_original_name', 'cover_letter',
        'source', 'external_reference',
        'status', 'scope_score', 'scope_reason', 'final_score', 'score_breakdown',
        'ai_summary', 'candidate_feedback', 'recruiter_rank', 'read_at', 'selected_at', 'notified_at', 'cv_deleted_at', 'status_token_hash',
        'processing_stage', 'processing_error', 'screening_reviewed_by', 'screening_reviewed_at',
        'notification_state', 'notification_error', 'notification_attempted_at', 'notification_message_id',
        'personal_data_deleted_at',
    ];

    protected $hidden = ['id', 'job_offer_id', 'status_token_hash', 'cv_path'];

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->public_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'scope_score' => 'float', 'final_score' => 'float', 'score_breakdown' => 'array',
            'read_at' => 'datetime', 'selected_at' => 'datetime', 'notified_at' => 'datetime', 'cv_deleted_at' => 'datetime',
            'screening_reviewed_at' => 'datetime', 'notification_attempted_at' => 'datetime',
            'personal_data_deleted_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(ApplicationAnnotation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
