<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'company', 'location', 'description', 'criteria',
        'rejection_message', 'final_rejection_message', 'screening_provider', 'screening_model',
        'scoring_provider', 'scoring_model', 'status', 'opens_at', 'closes_at', 'closure_requested_at',
        'finalized_at', 'selected_application_id', 'ingestion_key_hash', 'organization_id',
    ];

    protected $hidden = ['id', 'user_id', 'organization_id', 'selected_application_id', 'ingestion_key_hash'];

    protected static function booted(): void
    {
        static::creating(function (self $offer): void {
            $offer->public_id ??= (string) Str::uuid7();
            $offer->organization_id ??= $offer->user_id
                ? User::query()->whereKey($offer->user_id)->value('organization_id')
                : null;
        });
    }

    protected function casts(): array
    {
        return [
            'criteria' => 'array', 'opens_at' => 'datetime', 'closes_at' => 'datetime',
            'closure_requested_at' => 'datetime', 'finalized_at' => 'datetime',
        ];
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
