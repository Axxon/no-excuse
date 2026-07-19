<?php

namespace App\Contracts;

use App\Data\PseudonymizationResult;

interface CvPseudonymizer
{
    /** @param list<string> $professionalTerms */
    public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): PseudonymizationResult;
}
