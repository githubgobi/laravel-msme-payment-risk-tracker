<?php

namespace App\Enums;

enum AlertType: string
{
    case T10Warning      = 't10_warning';
    case T3Urgent        = 't3_urgent';
    case Overdue         = 'overdue';
    case YearEndSummary  = 'year_end_summary';

    public function label(): string
    {
        return match($this) {
            self::T10Warning     => '10-Day Warning',
            self::T3Urgent       => '3-Day Urgent Alert',
            self::Overdue        => 'Overdue Notice',
            self::YearEndSummary => 'Year-End Exposure Summary',
        };
    }

    public function daysBeforeDeadline(): ?int
    {
        return match($this) {
            self::T10Warning     => 10,
            self::T3Urgent       => 3,
            self::Overdue        => 0,
            self::YearEndSummary => null,
        };
    }
}
