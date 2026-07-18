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
        'scoring_provider', 'scoring_model', 'status', 'opens_at', 'closes_at', 'ingestion_key_hash',
    ];

    protected $hidden = ['id', 'user_id', 'ingestion_key_hash'];

    protected static function booted(): void
    {
        static::creating(function (self $offer): void {
            $offer->public_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return ['criteria' => 'array', 'opens_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
