<?php

namespace App\Console\Commands;

use App\Mail\DemoSlotAvailableMail;
use App\Models\DemoWaitlistEntry;
use App\Services\DemoSandbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyDemoWaitlist extends Command
{
    protected $signature = 'demo:notify-waitlist';

    protected $description = 'Notify waiting visitors when public demo capacity is available';

    public function handle(DemoSandbox $sandbox): int
    {
        if (! config('no-excuse.public_demo.enabled')) {
            return self::SUCCESS;
        }

        $available = max(0, $sandbox->maxSessions() - $sandbox->activeCount());
        $entries = DemoWaitlistEntry::query()->where('status', 'waiting')->oldest()->limit($available)->get();
        foreach ($entries as $entry) {
            Mail::to($entry->email)->send(new DemoSlotAvailableMail($entry->locale));
            $entry->update(['status' => 'notified', 'notified_at' => now()]);
        }
        $this->info($entries->count().' waitlist notification(s) sent.');

        return self::SUCCESS;
    }
}
