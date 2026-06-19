<?php

namespace App\Enums;

enum AlertChannel: string
{
    case Email     = 'email';
    case Whatsapp  = 'whatsapp';
    case Sms       = 'sms';

    public function label(): string
    {
        return match($this) {
            self::Email    => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Sms      => 'SMS',
        };
    }
}
