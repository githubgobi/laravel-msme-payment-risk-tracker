<?php

namespace App\DTOs;

use App\Enums\VendorCategory;

readonly class LlmClassificationResult
{
    public function __construct(
        public VendorCategory $category,
        public float          $confidence,
        public string         $reasoning,
        public bool           $autoApplied,
    ) {}
}
