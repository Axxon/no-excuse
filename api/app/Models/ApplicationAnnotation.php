<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationAnnotation extends Model
{
    protected $fillable = ['body'];

    protected $hidden = ['id', 'application_id', 'user_id'];

    protected static function booted(): void
    {
        static::creating(function (self $annotation): void {
            $annotation->public_id ??= (string) Str::uuid7();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
