<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner   = 'owner';
    case Admin   = 'admin';
    case Finance = 'finance';
    case Viewer  = 'viewer';

    public function label(): string
    {
        return match($this) {
            self::Owner   => 'Owner',
            self::Admin   => 'Admin',
            self::Finance => 'Finance Manager',
            self::Viewer  => 'Viewer',
        };
    }

    public function canManageVendors(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance]);
    }

    public function canManageInvoices(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance]);
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::Owner, self::Admin]);
    }

    public function canViewReports(): bool
    {
        return true;
    }
}
