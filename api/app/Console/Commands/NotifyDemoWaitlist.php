<?php

namespace App\Console\Commands;

use App\Mail\DemoSlotAvailableMail;
use App\Models\DemoWaitlistEntry;
use App\Services\DemoSandbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class NotifyDemoWaitlist extends Command
{
    protected $signature = 'demo:notify-waitlist';

    protected $description = 'Notify waiting visitors when public demo capacity is available';

    public function handle(DemoSandbox $sandbox): int
    {
        if (! config('no-excuse.public_demo.enabled')) {
            return self::SUCCESS;
        }

        $entries = Cache::lock('no-excuse:demo-session-capacity', 30)->block(5, function () use ($sandbox) {
            $sandbox->pruneExpired();
            $sandbox->releaseExpiredReservations();
            $available = max(0, $sandbox->maxSessions() - $sandbox->activeCount() - $sandbox->reservedCount());
            $entries = DemoWaitlistEntry::query()->where('status', 'waiting')->oldest()->limit($available)->get();
            foreach ($entries as $entry) {
                $token = Str::random(64);
                $entry->update([
                    'status' => 'notified', 'notified_at' => now(),
                    'access_token_hash' => hash('sha256', $token), 'reserved_until' => now()->addMinutes(30),
                ]);
                $entry->setAttribute('plain_access_token', $token);
            }

            return $entries;
        });

        $sent = 0;
        foreach ($entries as $entry) {
            try {
                Mail::to($entry->email)->send(new DemoSlotAvailableMail($entry->locale, $entry->getAttribute('plain_access_token')));
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $entry->update(['status' => 'waiting', 'notified_at' => null, 'access_token_hash' => null, 'reserved_until' => null]);
            }
        }
        $this->info($sent.' waitlist notification(s) sent.');

        return self::SUCCESS;
    }
}
