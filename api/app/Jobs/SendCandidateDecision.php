<?php

namespace App\Jobs;

use App\Mail\CandidateDecisionMail;
use App\Models\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCandidateDecision implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $application = Application::query()->with('offer.organization')->find($this->applicationId);
        if (! $application) {
            return;
        }
        if ($application->offer->organization?->is_demo) {
            $application->update(['notified_at' => now()]);
            $application->events()->create(['type' => 'candidate_notification_previewed', 'metadata' => ['status' => $application->status]]);

            return;
        }
        Mail::to($application->candidate_email)->send(new CandidateDecisionMail($application));
        $application->update(['notified_at' => now()]);
        $application->events()->create(['type' => 'candidate_notified', 'metadata' => ['status' => $application->status]]);
    }
}
