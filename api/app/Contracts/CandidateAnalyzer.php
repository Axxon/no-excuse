<?php

namespace App\Contracts;

use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Models\JobOffer;

interface CandidateAnalyzer
{
    public function screen(JobOffer $offer, string $cvText): ScreeningResult;

    public function score(JobOffer $offer, string $cvText): ScoringResult;
}
