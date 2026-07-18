<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DemoWaitlistEntry;
use Illuminate\Support\Facades\Storage;

class ApplicationRetention
{
    private const REJECTED_STATUSES = ['rejected_out_of_scope', 'rejected_final'];

    public function purgeRejectedCv(Application $application, bool $force = false): bool
    {
        $application->loadMissing('offer.organization');
        if ($application->offer->organization?->is_demo
            || ! in_array($application->status, self::REJECTED_STATUSES, true)
            || ! $application->notified_at
            || ! $application->cv_path
            || (! $force && $application->notified_at->gt(now()->subDays($this->rejectedCvDays($application))))) {
            return false;
        }

        Storage::disk('local')->delete($application->cv_path);
        $application->update([
            'cv_path' => null,
            'cv_original_name' => null,
            'cv_deleted_at' => now(),
        ]);
        $application->events()->create([
            'type' => 'cv_deleted_by_retention',
            'metadata' => ['policy' => 'rejected_cv', 'retained' => ['status', 'scores', 'timestamps', 'events']],
        ]);

        return true;
    }

    public function run(): int
    {
        $count = 0;
        Application::query()
            ->whereIn('status', self::REJECTED_STATUSES)
            ->whereNotNull('notified_at')
            ->whereNotNull('cv_path')
            ->with('offer.organization')
            ->chunkById(100, function ($applications) use (&$count): void {
                foreach ($applications as $application) {
                    if ($application->notified_at->lte(now()->subDays($this->rejectedCvDays($application)))) {
                        $count += $this->purgeRejectedCv($application, true) ? 1 : 0;
                    }
                }
            });

        Application::query()->where('status', 'selected')->whereNotNull('notified_at')->whereNotNull('cv_path')
            ->with('offer.organization')->chunkById(100, function ($applications) use (&$count): void {
                foreach ($applications as $application) {
                    $days = max(1, (int) ($application->offer->organization?->selected_cv_retention_days ?? config('no-excuse.retention.selected_cv_days')));
                    if ($application->notified_at->lte(now()->subDays($days))) {
                        $count += $this->purgeCv($application, 'selected_cv') ? 1 : 0;
                    }
                }
            });

        Application::query()->whereNotNull('notified_at')->whereNull('personal_data_deleted_at')
            ->with('offer.organization')->chunkById(100, function ($applications) use (&$count): void {
                foreach ($applications as $application) {
                    $days = max(30, (int) ($application->offer->organization?->candidate_data_retention_days ?? config('no-excuse.retention.candidate_data_days')));
                    if ($application->notified_at->lte(now()->subDays($days))) {
                        $this->anonymize($application);
                        $count++;
                    }
                }
            });

        DemoWaitlistEntry::query()
            ->where(fn ($query) => $query->where('created_at', '<=', now()->subDays(max(1, (int) config('no-excuse.retention.waitlist_days'))))
                ->orWhere(fn ($query) => $query->whereNotNull('notified_at')->where('notified_at', '<=', now()->subDays(7))))
            ->delete();

        return $count;
    }

    public function anonymize(Application $application): void
    {
        if ($application->cv_path) {
            Storage::disk('local')->delete($application->cv_path);
        }
        $application->annotations()->delete();
        $application->events()->get()->each(function ($event): void {
            $metadata = $event->metadata;
            if (is_array($metadata) && array_key_exists('external_reference', $metadata)) {
                unset($metadata['external_reference']);
                $event->update(['metadata' => $metadata]);
            }
        });
        $application->update([
            'candidate_name' => 'Candidat supprimé',
            'candidate_email' => 'deleted+'.$application->public_id.'@invalid.local',
            'cover_letter' => null,
            'candidate_feedback' => null,
            'external_reference' => null,
            'scope_reason' => null,
            'score_breakdown' => null,
            'ai_summary' => null,
            'notification_error' => null,
            'cv_path' => null,
            'cv_original_name' => null,
            'cv_deleted_at' => $application->cv_deleted_at ?? now(),
            'personal_data_deleted_at' => now(),
        ]);
        $application->events()->create(['type' => 'personal_data_anonymized', 'metadata' => ['policy' => 'candidate_data']]);
    }

    private function rejectedCvDays(Application $application): int
    {
        return max(0, (int) ($application->offer->organization?->rejected_cv_retention_days ?? config('no-excuse.retention.rejected_cv_days')));
    }

    private function purgeCv(Application $application, string $policy): bool
    {
        if (! $application->cv_path) {
            return false;
        }
        Storage::disk('local')->delete($application->cv_path);
        $application->update(['cv_path' => null, 'cv_original_name' => null, 'cv_deleted_at' => now()]);
        $application->events()->create(['type' => 'cv_deleted_by_retention', 'metadata' => ['policy' => $policy]]);

        return true;
    }
}
