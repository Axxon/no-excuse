<?php

namespace App\Data;

final readonly class ScoringResult
{
    /** @param array<string, float> $breakdown */
    public function __construct(public float $score, public array $breakdown, public string $summary) {}
}
