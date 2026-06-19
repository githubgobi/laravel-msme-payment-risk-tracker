<?php

namespace App\DTOs;

/**
 * Normalized row from any import source (CSV, Excel, Tally XML).
 * All values are raw strings — RowValidator handles parsing and casting.
 */
final class ImportRow
{
    public function __construct(
        public readonly int    $rowNumber,
        public readonly string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly string $vendorName,
        public readonly string $amount,
        public readonly string $gstin          = '',
        public readonly string $udyamNumber    = '',
        public readonly string $paidAmount     = '0',
        public readonly string $agreementExists = 'false',
        public readonly string $narration      = '',
    ) {}
}
