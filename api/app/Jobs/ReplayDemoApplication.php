<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\DemoAnalysisCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use Throwable;

class ReplayDemoApplication implements ShouldQueue
{
    use Queueable;

    public const SCREENING_STARTED = 'screening_started';

    public const SCREENING_COMPLETED = 'screening_completed';

    public const SCORING_STARTED = 'scoring_started';

    public const SCORING_COMPLETED = 'scoring_completed';

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $applicationId,
        public int $candidateIndex,
        public string $stage = self::SCREENING_STARTED,
    ) {
        $this->onQueue(str_starts_with($stage, 'scoring_') ? 'candidate-scoring' : 'candidate-intake');
    }

    public function handle(DemoAnalysisCatalog $catalog): void
    {
        $application = Application::query()->with('offer.organization')->find($this->applicationId);
        if (! $application || ! $application->offer->organization?->is_demo) {
            return;
        }

        $analysis = $catalog->for($this->candidateIndex);
        $delay = max(0, (int) config('no-excuse.public_demo.processing_delay_seconds'));

        match ($this->stage) {
            self::SCREENING_STARTED => $this->startScreening($application, $delay),
            self::SCREENING_COMPLETED => $this->completeScreening($application, $analysis, $delay),
            self::SCORING_STARTED => $this->startScoring($application, $delay),
            self::SCORING_COMPLETED => $this->completeScoring($application, $analysis),
            default => throw new InvalidArgumentException("Unknown demo replay stage: {$this->stage}"),
        };
    }

    private function startScreening(Application $application, int $delay): void
    {
        if ($application->status !== 'received') {
            return;
        }

        $application->update(['status' => 'screening']);
        self::dispatch($application->id, $this->candidateIndex, self::SCREENING_COMPLETED)
            ->delay(now()->addSeconds($delay));
    }

    /** @param array{in_scope: bool, scope_score: float, scope_reason: string} $analysis */
    private function completeScreening(Application $application, array $analysis, int $delay): void
    {
        if ($application->status !== 'screening') {
            return;
        }

        $application->update([
            'scope_score' => $analysis['scope_score'],
            'scope_reason' => $analysis['scope_reason'],
            'status' => $analysis['in_scope'] ? 'qualified' : 'rejected_out_of_scope',
        ]);
        $application->events()->create([
            'type' => 'screened',
            'metadata' => ['in_scope' => $analysis['in_scope'], 'score' => $analysis['scope_score'], 'source' => 'precomputed_demo'],
        ]);

        if ($analysis['in_scope']) {
            self::dispatch($application->id, $this->candidateIndex, self::SCORING_STARTED)
                ->delay(now()->addSeconds($delay));

            return;
        }

        $application->events()->create(['type' => 'candidate_notification_queued', 'metadata' => ['status' => $application->status]]);
        SendCandidateDecision::dispatch($application->id)->onQueue('notifications');
    }

    private function startScoring(Application $application, int $delay): void
    {
        if ($application->status !== 'qualified') {
            return;
        }

        $application->update(['status' => 'scoring']);
        self::dispatch($application->id, $this->candidateIndex, self::SCORING_COMPLETED)
            ->delay(now()->addSeconds($delay));
    }

    /**
     * @param array{
     *     final_score: float|null,
     *     score_breakdown: array<string, float>|null,
     *     summary: string|null
     * } $analysis
     */
    private function completeScoring(Application $application, array $analysis): void
    {
        if ($application->status !== 'scoring' || $analysis['final_score'] === null) {
            return;
        }

        $application->update([
            'status' => 'scored',
            'final_score' => $analysis['final_score'],
            'score_breakdown' => $analysis['score_breakdown'],
            'ai_summary' => $analysis['summary'],
        ]);
        $application->events()->create([
            'type' => 'scored',
            'metadata' => ['score' => $analysis['final_score'], 'source' => 'precomputed_demo'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Application::query()->whereKey($this->applicationId)->update(['status' => 'processing_failed']);
    }
}
