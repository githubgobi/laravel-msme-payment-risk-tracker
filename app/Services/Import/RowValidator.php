<?php

namespace App\Services\Import;

use App\DTOs\ImportRow;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Validates and parses a single ImportRow.
 *
 * Pure class — no database access, no framework coupling.
 * Returns an array of human-readable error strings (empty = valid).
 */
final class RowValidator
{
    // Ordered list of date formats tried before falling back to Carbon::parse()
    private const DATE_FORMATS = [
        'Y-m-d',    // 2025-01-15 (ISO)
        'd-m-Y',    // 15-01-2025 (Indian dash)
        'd/m/Y',    // 15/01/2025 (Indian slash)
        'Ymd',      // 20250115  (Tally)
        'd-M-Y',    // 15-Jan-2025
        'd M Y',    // 15 Jan 2025
        'j/n/Y',    // 1/1/2025 (no leading zeros)
    ];

    private const GSTIN_PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    private const UDYAM_PATTERN = '/^UDYAM-[A-Z]{2}-\d{2}-\d{7}$/';

    /**
     * Validate the row and return an array of error strings.
     * Empty array = valid row.
     *
     * @return string[]
     */
    public function validate(ImportRow $row): array
    {
        $errors = [];

        // invoice_number — required, max 100 chars
        $invNum = trim($row->invoiceNumber);
        if ($invNum === '') {
            $errors[] = 'invoice_number is required';
        } elseif (strlen($invNum) > 100) {
            $errors[] = 'invoice_number must not exceed 100 characters';
        }

        // invoice_date — required, parseable
        $dateStr = trim($row->invoiceDate);
        if ($dateStr === '') {
            $errors[] = 'invoice_date is required';
        } elseif (! $this->isValidDate($dateStr)) {
            $errors[] = "invoice_date '{$dateStr}' is not a recognizable date (expected formats: DD-MM-YYYY, YYYY-MM-DD, YYYYMMDD)";
        }

        // vendor_name — required, min 2, max 200
        $vendorName = trim($row->vendorName);
        if ($vendorName === '') {
            $errors[] = 'vendor_name is required';
        } elseif (strlen($vendorName) < 2) {
            $errors[] = 'vendor_name must be at least 2 characters';
        } elseif (strlen($vendorName) > 200) {
            $errors[] = 'vendor_name must not exceed 200 characters';
        }

        // amount — required, numeric, not null
        $amountStr = $this->stripCommas($row->amount);
        if (trim($amountStr) === '') {
            $errors[] = 'amount is required';
        } elseif (! is_numeric($amountStr)) {
            $errors[] = "amount '{$row->amount}' must be a number";
        }

        // paid_amount — optional but must be numeric if present
        $paidStr = $this->stripCommas($row->paidAmount);
        if ($paidStr !== '' && $paidStr !== '0' && ! is_numeric($paidStr)) {
            $errors[] = "paid_amount '{$row->paidAmount}' must be a number";
        }

        // gstin — optional, but if present must match format
        $gstin = strtoupper(trim($row->gstin));
        if ($gstin !== '' && ! preg_match(self::GSTIN_PATTERN, $gstin)) {
            $errors[] = "gstin '{$row->gstin}' is not a valid GSTIN (15-character format required)";
        }

        // udyam_number — optional, but if present must match format
        $udyam = strtoupper(trim($row->udyamNumber));
        if ($udyam !== '' && ! preg_match(self::UDYAM_PATTERN, $udyam)) {
            $errors[] = "udyam_number '{$row->udyamNumber}' is not a valid format (expected: UDYAM-XX-00-0000000)";
        }

        return $errors;
    }

    /**
     * Parse a date string into a Carbon instance.
     * Returns null if none of the formats match.
     */
    public function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date !== false && $date->format($format) === $raw) {
                    return $date->startOfDay();
                }
            } catch (InvalidFormatException) {
                // try next format
            }
        }

        // Last-resort: Carbon::parse (handles ISO 8601 and common EN strings)
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Parse and sanitize an amount string.
     * Strips Indian-style commas (5,00,000 → 500000).
     * Returns null if not numeric.
     */
    public function parseAmount(string $raw): ?float
    {
        $stripped = $this->stripCommas($raw);
        if (! is_numeric($stripped)) {
            return null;
        }

        return (float) $stripped;
    }

    /** Parse agreement_exists from various truthy string representations. */
    public function parseAgreementExists(string $raw): bool
    {
        return in_array(strtolower(trim($raw)), ['true', '1', 'yes', 'y'], true);
    }

    private function isValidDate(string $raw): bool
    {
        return $this->parseDate($raw) !== null;
    }

    private function stripCommas(string $value): string
    {
        return str_replace(',', '', trim($value));
    }
}
