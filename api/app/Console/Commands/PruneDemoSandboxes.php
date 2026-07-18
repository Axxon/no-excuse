<?php

namespace App\Console\Commands;

use App\Services\DemoSandbox;
use Illuminate\Console\Command;

class PruneDemoSandboxes extends Command
{
    protected $signature = 'demo:prune';

    protected $description = 'Delete expired public demo sandboxes and their fictional CV files';

    public function handle(DemoSandbox $sandbox): int
    {
        $count = $sandbox->pruneExpired();
        $this->info($count.' expired demo sandbox(es) deleted.');

        return self::SUCCESS;
    }
}
