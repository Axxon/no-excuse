<?php

namespace App\Console\Commands;

use App\Services\ApplicationRetention;
use Illuminate\Console\Command;

class ApplyApplicationRetention extends Command
{
    protected $signature = 'applications:apply-retention';

    protected $description = 'Delete rejected CV files according to the configured retention policy';

    public function handle(ApplicationRetention $retention): int
    {
        $this->info($retention->run().' rejected CV file(s) deleted.');

        return self::SUCCESS;
    }
}
