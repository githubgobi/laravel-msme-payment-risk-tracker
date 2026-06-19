<?php

namespace App\Enums;

enum VendorVerificationSource: string
{
    case Manual   = 'manual';
    case Api      = 'api';
    case Llm      = 'llm';

    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manually Tagged',
            self::Api    => 'Udyam API Verified',
            self::Llm    => 'AI Classified',
        };
    }
}
