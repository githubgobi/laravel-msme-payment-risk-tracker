<?php

namespace App\DTOs;

readonly class LlmMatchResult
{
    public function __construct(
        public int    $vendorId,
        public string $vendorName,
        public float  $confidence,
        public string $reasoning,
    ) {}
}
