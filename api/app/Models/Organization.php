<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'notification_sender_name', 'notification_reply_to',
        'default_screening_provider', 'default_screening_model',
        'default_scoring_provider', 'default_scoring_model',
        'screening_workers', 'scoring_workers', 'screening_prompt', 'scoring_prompt',
        'is_demo', 'expires_at',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $organization): void {
            $organization->public_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'screening_workers' => 'integer',
            'scoring_workers' => 'integer',
            'is_demo' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function jobOffers(): HasMany
    {
        return $this->hasMany(JobOffer::class);
    }
}
