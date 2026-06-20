<?php

namespace App\Enums;

enum KnowledgeSourceType: string
{
    case Vendor = 'vendor';
    case Manual = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Vendor => 'Vendor Record',
            self::Manual => 'Manual Document',
        };
    }
}
