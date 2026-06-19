<?php

namespace App\Services\Import;

/**
 * Normalizes CSV column headers to canonical field names.
 *
 * Supports common variations used by Tally, Busy, SAP, and generic exports.
 * maatwebsite/excel already lowercases and underscores headers, so all
 * aliases here should be in that normalized form.
 */
final class ColumnMapper
{
    private const ALIASES = [
        'invoice_number' => [
            'invoice_number', 'invoice_no', 'bill_number', 'bill_no',
            'voucher_number', 'voucher_no', 'inv_no', 'ref_number',
            'reference_number', 'doc_number',
        ],
        'invoice_date' => [
            'invoice_date', 'bill_date', 'date', 'voucher_date',
            'transaction_date', 'doc_date', 'inv_date',
        ],
        'vendor_name' => [
            'vendor_name', 'party_name', 'supplier_name', 'ledger_name',
            'party', 'supplier', 'vendor', 'creditor_name',
        ],
        'amount' => [
            'amount', 'invoice_amount', 'bill_amount', 'total_amount',
            'gross_amount', 'net_amount', 'taxable_amount', 'value',
        ],
        'gstin' => [
            'gstin', 'gst_number', 'gst_no', 'vendor_gstin',
            'supplier_gstin', 'party_gstin',
        ],
        'udyam_number' => [
            'udyam_number', 'udyam_no', 'udyam_registration',
            'msme_number', 'udyam_registration_number',
        ],
        'paid_amount' => [
            'paid_amount', 'amount_paid', 'payment_amount', 'paid',
            'cleared_amount',
        ],
        'agreement_exists' => [
            'agreement_exists', 'has_agreement', 'written_agreement',
            'agreement', 'payment_terms_agreement',
        ],
        'narration' => [
            'narration', 'description', 'remarks', 'notes',
            'particulars', 'details',
        ],
    ];

    /**
     * Map a raw row (with possibly non-canonical keys) to canonical keys.
     * Unrecognized columns are dropped. Missing canonicals get null.
     *
     * @param  array<string, mixed>  $rawRow
     * @return array<string, mixed>
     */
    public function mapRow(array $rawRow): array
    {
        $normalized = [];

        foreach ($rawRow as $key => $value) {
            $canonical = $this->resolveCanonical((string) $key);
            if ($canonical !== null) {
                $normalized[$canonical] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Resolve a (already maatwebsite-normalized) header to its canonical name.
     * Returns null if the header is not recognized.
     */
    public function resolveCanonical(string $header): ?string
    {
        $header = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header)));

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($header, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /** Returns all recognized canonical field names. */
    public function canonicalFields(): array
    {
        return array_keys(self::ALIASES);
    }
}
