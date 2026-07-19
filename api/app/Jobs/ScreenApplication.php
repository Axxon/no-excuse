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

class ScreenApplication implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('candidate-intake');
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
        if (! in_array($application->status, ['received', 'screening'], true)) {
            return;
        }

        $application->update(['status' => 'screening', 'processing_stage' => 'screening', 'processing_error' => null]);
        $isDemo = $application->offer->organization?->is_demo;
        $selectedAnalyzer = $isDemo ? app(DemoCandidateAnalyzer::class) : $analyzer;
        $cvText = $isDemo ? $extractor->extract($application->cv_path) : $canonicalCv->for($application);
        $result = $selectedAnalyzer->screen($application, $cvText);
        $application->update([
            'scope_score' => $result->score,
            'scope_reason' => $result->reason,
            'status' => $result->inScope ? 'qualified' : ($application->offer->organization?->is_demo ? 'rejected_out_of_scope' : 'rejection_proposed'),
            'processing_stage' => null,
            'notification_state' => ! $result->inScope && $application->offer->organization?->is_demo ? 'pending' : 'none',
        ]);
        $application->events()->create(['type' => 'screened', 'metadata' => ['in_scope' => $result->inScope, 'score' => $result->score]]);

        if ($result->inScope) {
            ScoreApplication::dispatch($application->id)->onQueue('candidate-scoring');
        } elseif ($application->offer->organization?->is_demo) {
            $application->events()->create(['type' => 'candidate_notification_queued', 'metadata' => ['status' => $application->status]]);
            SendCandidateDecision::dispatch($application->id)->onQueue('notifications');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Application::query()->whereKey($this->applicationId)->update([
            'status' => 'processing_failed', 'processing_stage' => 'screening',
            'processing_error' => mb_substr($exception?->getMessage() ?? 'Échec inconnu', 0, 2000),
        ]);
    }
}
