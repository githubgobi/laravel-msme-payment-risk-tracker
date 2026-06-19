<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Created  = 'created';
    case Updated  = 'updated';
    case Deleted  = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match($this) {
            self::Created  => 'Created',
            self::Updated  => 'Updated',
            self::Deleted  => 'Deleted',
            self::Restored => 'Restored',
        };
    }
}
