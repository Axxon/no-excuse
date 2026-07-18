<?php

namespace App\Jobs;

use App\Contracts\CandidateAnalyzer;
use App\Models\Application;
use App\Services\CvTextExtractor;
use App\Services\DemoCandidateAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScreenApplication implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('candidate-intake');
    }

    public function handle(CandidateAnalyzer $analyzer, CvTextExtractor $extractor): void
    {
        $application = Application::query()->with('offer.organization')->find($this->applicationId);
        if (! $application) {
            return;
        }
        if (! in_array($application->status, ['received', 'screening', 'processing_failed'], true)) {
            return;
        }

        $application->update(['status' => 'screening']);
        $selectedAnalyzer = $application->offer->organization?->is_demo ? app(DemoCandidateAnalyzer::class) : $analyzer;
        $result = $selectedAnalyzer->screen($application->offer, $extractor->extract($application->cv_path));
        $application->update([
            'scope_score' => $result->score,
            'scope_reason' => $result->reason,
            'status' => $result->inScope ? 'qualified' : 'rejected_out_of_scope',
        ]);
        $application->events()->create(['type' => 'screened', 'metadata' => ['in_scope' => $result->inScope, 'score' => $result->score]]);

        if ($result->inScope) {
            ScoreApplication::dispatch($application->id)->onQueue('candidate-scoring');
        } else {
            SendCandidateDecision::dispatch($application->id)->onQueue('notifications');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Application::query()->whereKey($this->applicationId)->update(['status' => 'processing_failed']);
    }
}
