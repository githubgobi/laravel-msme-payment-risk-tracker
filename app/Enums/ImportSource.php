<?php

namespace App\Enums;

enum ImportSource: string
{
    case Csv      = 'csv';
    case TallyXml = 'tally_xml';
    case Manual   = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Csv      => 'CSV / Excel',
            self::TallyXml => 'Tally XML',
            self::Manual   => 'Manual Entry',
        };
    }
}
