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

    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');
        $maskedLocal = implode('.', array_map(self::maskSegment(...), explode('.', $local)));
        $domainParts = explode('.', $domain);
        $topLevelDomain = count($domainParts) > 1 ? array_pop($domainParts) : null;
        $maskedDomain = implode('.', array_map(self::maskSegment(...), $domainParts));

        return $maskedLocal.'@'.$maskedDomain.($topLevelDomain === null ? '' : '.'.$topLevelDomain);
    }

    private static function maskSegment(string $segment): string
    {
        $length = mb_strlen($segment);

        return match (true) {
            $length === 0 => '',
            $length === 1 => '*',
            $length === 2 => mb_substr($segment, 0, 1).'*',
            default => mb_substr($segment, 0, 1).str_repeat('*', $length - 2).mb_substr($segment, -1),
        };
    }
}
