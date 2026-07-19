<?php

namespace App\Jobs;

use App\Contracts\CandidateAnalyzer;
use App\Models\Application;
use App\Services\CanonicalCvText;
use App\Services\CvTextExtractor;
use App\Services\DemoCandidateAnalyzer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScoreApplication implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('candidate-scoring');
    }

    public function uniqueId(): string
    {
        return (string) $this->applicationId;
    }

    public function handle(CandidateAnalyzer $analyzer, CanonicalCvText $canonicalCv, CvTextExtractor $extractor): void
    {
        $application = Application::query()->with('offer.organization')->find($this->applicationId);
        if (! $application) {
            return;
        }
        if (! in_array($application->status, ['qualified', 'scoring'], true)) {
            return;
        }

        $application->update(['status' => 'scoring', 'processing_stage' => 'scoring', 'processing_error' => null]);
        $isDemo = $application->offer->organization?->is_demo;
        $selectedAnalyzer = $isDemo ? app(DemoCandidateAnalyzer::class) : $analyzer;
        $cvText = $isDemo ? $extractor->extract($application->cv_path) : $canonicalCv->for($application);
        $result = $selectedAnalyzer->score($application, $cvText);
        $application->update([
            'status' => 'scored',
            'final_score' => $result->score,
            'score_breakdown' => $result->breakdown,
            'ai_summary' => $result->summary,
            'processing_stage' => null,
        ]);
        $application->events()->create(['type' => 'scored', 'metadata' => ['score' => $result->score]]);
    }

    public function failed(?Throwable $exception): void
    {
        Application::query()->whereKey($this->applicationId)->update([
            'status' => 'processing_failed', 'processing_stage' => 'scoring',
            'processing_error' => mb_substr($exception?->getMessage() ?? 'Échec inconnu', 0, 2000),
        ]);
    }
}
