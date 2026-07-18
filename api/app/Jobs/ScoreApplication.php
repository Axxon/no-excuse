<?php

namespace App\Jobs;

use App\Contracts\CandidateAnalyzer;
use App\Models\Application;
use App\Services\CvTextExtractor;
use App\Services\DemoCandidateAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScoreApplication implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('candidate-scoring');
    }

    public function handle(CandidateAnalyzer $analyzer, CvTextExtractor $extractor): void
    {
        $application = Application::query()->with('offer.organization')->find($this->applicationId);
        if (! $application) {
            return;
        }
        if (! in_array($application->status, ['qualified', 'scoring', 'processing_failed'], true)) {
            return;
        }

        $application->update(['status' => 'scoring']);
        $selectedAnalyzer = $application->offer->organization?->is_demo ? app(DemoCandidateAnalyzer::class) : $analyzer;
        $result = $selectedAnalyzer->score($application->offer, $extractor->extract($application->cv_path));
        $application->update([
            'status' => 'scored',
            'final_score' => $result->score,
            'score_breakdown' => $result->breakdown,
            'ai_summary' => $result->summary,
        ]);
        $application->events()->create(['type' => 'scored', 'metadata' => ['score' => $result->score]]);
    }

    public function failed(?Throwable $exception): void
    {
        Application::query()->whereKey($this->applicationId)->update(['status' => 'processing_failed']);
    }
}
