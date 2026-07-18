<?php

namespace App\Services;

use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

class FinalizeOffer
{
    private const BLOCKING_STATUSES = ['received', 'screening', 'qualified', 'scoring', 'processing_failed', 'rejection_proposed'];

    public function request(JobOffer $offer): bool
    {
        return DB::transaction(function () use ($offer): bool {
            $lockedOffer = JobOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->status === 'selection_made') {
                return true;
            }
            if ($lockedOffer->status === 'open') {
                $lockedOffer->update(['status' => 'closing', 'closure_requested_at' => now()]);
            }
            if ($lockedOffer->applications()->whereIn('status', self::BLOCKING_STATUSES)->exists()) {
                return false;
            }

            $this->buildTopTen($lockedOffer);

            return true;
        });
    }

    public function handle(JobOffer $offer): void
    {
        $this->request($offer);
    }

    private function buildTopTen(JobOffer $offer): void
    {
        $offer->applications()
            ->whereIn('status', ['scored', 'shortlisted'])
            ->update(['status' => 'scored', 'recruiter_rank' => null]);

        $offer->applications()
            ->where('status', 'scored')
            ->orderByDesc('final_score')
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->each(function ($application, int $index): void {
                $application->update(['status' => 'shortlisted', 'recruiter_rank' => $index + 1]);
                $application->events()->create(['type' => 'shortlisted', 'metadata' => ['rank' => $index + 1]]);
            });

        $offer->update(['status' => 'closed', 'finalized_at' => now()]);
    }
}
