<?php

namespace App\Enums;

enum VendorCategory: string
{
    case Micro         = 'micro';
    case Small         = 'small';
    case Medium        = 'medium';
    case Large         = 'large';
    case Unclassified  = 'unclassified';

    public function label(): string
    {
        return match($this) {
            self::Micro        => 'Micro',
            self::Small        => 'Small',
            self::Medium       => 'Medium',
            self::Large        => 'Large',
            self::Unclassified => 'Unclassified',
        };
    }

    /** Returns true if Section 43B(h) applies to this vendor category. */
    public function isSubjectTo43Bh(): bool
    {
        return in_array($this, [self::Micro, self::Small]);
    }
}
