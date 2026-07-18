<?php

namespace App\Jobs;

use App\Mail\CandidateDecisionMail;
use App\Models\Application;
use App\Services\ApplicationRetention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendCandidateDecision implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(ApplicationRetention $retention): void
    {
        $messageId = DB::transaction(function (): ?string {
            $application = Application::query()->lockForUpdate()->find($this->applicationId);
            if (! $application || $application->notified_at || in_array($application->notification_state, ['sent', 'previewed', 'sending', 'attention_required'], true)) {
                return null;
            }
            $messageId = (string) Str::uuid7();
            $application->update([
                'notification_state' => 'sending',
                'notification_attempted_at' => now(),
                'notification_message_id' => $messageId,
                'notification_error' => null,
            ]);

            return $messageId;
        });
        if (! $messageId) {
            return;
        }
        $application = Application::query()->with('offer.organization')->findOrFail($this->applicationId);
        if ($application->offer->organization?->is_demo) {
            $application->update(['notified_at' => now(), 'notification_state' => 'previewed']);
            $application->events()->create(['type' => 'candidate_notification_previewed', 'metadata' => ['status' => $application->status]]);

            return;
        }
        try {
            Mail::to($application->candidate_email)->send(new CandidateDecisionMail($application));
            $application->update(['notified_at' => now(), 'notification_state' => 'sent']);
            $application->events()->create(['type' => 'candidate_notified', 'metadata' => ['status' => $application->status, 'message_id' => $messageId]]);
            $retention->purgeRejectedCv($application->fresh());
        } catch (Throwable $exception) {
            $application->update(['notification_state' => 'failed', 'notification_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            $application->events()->create(['type' => 'candidate_notification_failed', 'metadata' => ['message_id' => $messageId]]);

            throw $exception;
        }
    }
}
