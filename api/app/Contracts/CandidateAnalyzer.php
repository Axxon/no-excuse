<?php

namespace App\Contracts;

use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Models\Application;

interface CandidateAnalyzer
{
    public function screen(Application $application, string $cvText): ScreeningResult;

    public function score(Application $application, string $cvText): ScoringResult;
}
