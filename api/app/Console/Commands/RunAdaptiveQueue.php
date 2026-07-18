<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class RunAdaptiveQueue extends Command
{
    protected $signature = 'queue:adaptive {stage : screening or scoring}';

    protected $description = 'Keep the configured number of queue workers running for a recruitment stage';

    /** @var list<Process> */
    private array $workers = [];

    private bool $running = true;

    public function handle(): int
    {
        $stage = (string) $this->argument('stage');
        $configuration = match ($stage) {
            'screening' => ['field' => 'screening_workers', 'queue' => 'candidate-intake'],
            'scoring' => ['field' => 'scoring_workers', 'queue' => 'candidate-scoring'],
            default => null,
        };
        if (! $configuration) {
            $this->error('Stage must be screening or scoring.');

            return self::INVALID;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->running = false);
        pcntl_signal(SIGINT, fn () => $this->running = false);

        while ($this->running) {
            $this->workers = array_values(array_filter($this->workers, fn (Process $worker) => $worker->isRunning()));
            $desired = $this->desiredWorkers($configuration['field']);

            while (count($this->workers) < $desired) {
                $this->workers[] = $this->startWorker($configuration['queue']);
            }
            while (count($this->workers) > $desired) {
                array_pop($this->workers)?->stop(10, SIGTERM);
            }

            usleep(5_000_000);
        }

        foreach ($this->workers as $worker) {
            $worker->stop(10, SIGTERM);
        }

        return self::SUCCESS;
    }

    private function desiredWorkers(string $field): int
    {
        try {
            return max(1, min(10, (int) (Organization::query()->value($field) ?? 1)));
        } catch (Throwable) {
            return 1;
        }
    }

    private function startWorker(string $queue): Process
    {
        $process = new Process([
            PHP_BINARY, $this->laravel->basePath('artisan'), 'queue:work', 'redis',
            '--queue='.$queue, '--sleep=1', '--tries=3', '--timeout=120', '--max-time=300',
        ], $this->laravel->basePath());
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();

        return $process;
    }
}
