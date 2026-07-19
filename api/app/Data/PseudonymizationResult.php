<?php

namespace App\Data;

final readonly class PseudonymizationResult
{
    public function __construct(public string $text, public string $version) {}
}
