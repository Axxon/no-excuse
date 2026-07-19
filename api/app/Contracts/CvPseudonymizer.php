<?php

namespace App\Contracts;

interface CvPseudonymizer
{
    /** @param list<string> $professionalTerms */
    public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): string;
}
