<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DemoWaitlistEntry extends Model
{
    protected $fillable = ['email', 'email_hash', 'locale', 'status', 'notified_at'];

    protected $hidden = ['id', 'email', 'email_hash'];

    protected static function booted(): void
    {
        static::creating(fn (self $entry) => $entry->public_id ??= (string) Str::uuid7());
    }

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'notified_at' => 'datetime'];
    }
}
