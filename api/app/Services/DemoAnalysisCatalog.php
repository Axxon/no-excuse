<?php

namespace App\Services;

use InvalidArgumentException;

class DemoAnalysisCatalog
{
    /**
     * @return array{
     *     in_scope: bool,
     *     scope_score: float,
     *     scope_reason: string,
     *     final_score: float|null,
     *     score_breakdown: array<string, float>|null,
     *     summary: string|null
     * }
     */
    public function for(int $candidateIndex): array
    {
        $analyses = require resource_path('demo/analyses.php');
        $analysis = $analyses[$candidateIndex] ?? null;

        if (! is_array($analysis)) {
            throw new InvalidArgumentException("Missing precomputed demo analysis for candidate {$candidateIndex}.");
        }

        return $analysis;
    }
}
