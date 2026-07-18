<?php

namespace App\Services;

use App\Models\Application;
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
            || (! $force && $application->notified_at->gt(now()->subDays(max(0, (int) config('no-excuse.retention.rejected_cv_days')))))) {
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
            ->where('notified_at', '<=', now()->subDays(max(0, (int) config('no-excuse.retention.rejected_cv_days'))))
            ->with('offer.organization')
            ->chunkById(100, function ($applications) use (&$count): void {
                foreach ($applications as $application) {
                    $count += $this->purgeRejectedCv($application, true) ? 1 : 0;
                }
            });

        return $count;
    }
}
