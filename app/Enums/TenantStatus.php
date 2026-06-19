<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial    = 'trial';
    case Active   = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Trial     => 'Trial',
            self::Active    => 'Active',
            self::Inactive  => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function isAccessible(): bool
    {
        return in_array($this, [self::Trial, self::Active]);
    }
}
