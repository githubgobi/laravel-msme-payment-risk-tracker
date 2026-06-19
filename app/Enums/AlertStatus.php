<?php

namespace App\Enums;

enum AlertStatus: string
{
    case Pending   = 'pending';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Failed    = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Sent      => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed    => 'Failed',
        };
    }
}
