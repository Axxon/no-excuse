<?php

namespace App\Data;

final readonly class ScreeningResult
{
    public function __construct(public bool $inScope, public float $score, public string $reason) {}
}
