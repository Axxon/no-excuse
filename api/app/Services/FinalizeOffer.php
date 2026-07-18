<?php

namespace App\Services;

use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

class FinalizeOffer
{
    public function handle(JobOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
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

            $offer->update(['status' => 'closed']);
        });
    }
}
