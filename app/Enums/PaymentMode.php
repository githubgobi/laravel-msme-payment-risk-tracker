<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Neft   = 'neft';
    case Rtgs   = 'rtgs';
    case Imps   = 'imps';
    case Upi    = 'upi';
    case Cheque = 'cheque';
    case Cash   = 'cash';
    case Other  = 'other';

    public function label(): string
    {
        return match($this) {
            self::Neft   => 'NEFT',
            self::Rtgs   => 'RTGS',
            self::Imps   => 'IMPS',
            self::Upi    => 'UPI',
            self::Cheque => 'Cheque',
            self::Cash   => 'Cash',
            self::Other  => 'Other',
        };
    }
}
