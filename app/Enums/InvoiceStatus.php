<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending    = 'pending';
    case Partial    = 'partial';
    case Paid       = 'paid';
    case Overdue    = 'overdue';
    case Disallowed = 'disallowed';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Pending',
            self::Partial    => 'Partially Paid',
            self::Paid       => 'Fully Paid',
            self::Overdue    => 'Overdue',
            self::Disallowed => 'Disallowed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending    => 'warning',
            self::Partial    => 'info',
            self::Paid       => 'success',
            self::Overdue    => 'danger',
            self::Disallowed => 'danger',
        };
    }

    public function isAtRisk(): bool
    {
        return in_array($this, [self::Pending, self::Partial, self::Overdue]);
    }
}
