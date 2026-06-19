<?php

namespace App\Services\Import;

use App\DTOs\ImportRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Parses a CSV or Excel file into a collection of ImportRow DTOs.
 *
 * Uses maatwebsite/excel which:
 *   - Handles .csv, .xlsx, .xls
 *   - Normalizes headers: lowercase + spaces→underscores
 *   - Skips the header row automatically (WithHeadingRow)
 */
final class CsvImporter
{
    public function __construct(
        private readonly ColumnMapper $columnMapper,
    ) {}

    /**
     * Parse the file and return a Collection of ImportRow DTOs.
     *
     * @param  string  $absolutePath  Full filesystem path to the uploaded file
     * @return Collection<ImportRow>
     */
    public function parse(string $absolutePath): Collection
    {
        $import = new class implements ToCollection, WithHeadingRow {
            public Collection $rows;

            public function __construct()
            {
                $this->rows = collect();
            }

            public function collection(Collection $rows): void
            {
                $this->rows = $rows;
            }
        };

        Excel::import($import, $absolutePath);

        $rows       = collect();
        $rowNumber  = 2; // row 1 = header

        foreach ($import->rows as $raw) {
            $rawArray = $raw->toArray();

            // Skip completely empty rows
            $nonEmpty = array_filter($rawArray, fn ($v) => $v !== null && $v !== '');
            if (empty($nonEmpty)) {
                $rowNumber++;
                continue;
            }

            $mapped = $this->columnMapper->mapRow($rawArray);

            $rows->push(new ImportRow(
                rowNumber:       $rowNumber,
                invoiceNumber:   (string) ($mapped['invoice_number'] ?? ''),
                invoiceDate:     (string) ($mapped['invoice_date'] ?? ''),
                vendorName:      (string) ($mapped['vendor_name'] ?? ''),
                amount:          (string) ($mapped['amount'] ?? ''),
                gstin:           (string) ($mapped['gstin'] ?? ''),
                udyamNumber:     (string) ($mapped['udyam_number'] ?? ''),
                paidAmount:      (string) ($mapped['paid_amount'] ?? '0'),
                agreementExists: (string) ($mapped['agreement_exists'] ?? 'false'),
                narration:       (string) ($mapped['narration'] ?? ''),
            ));

            $rowNumber++;
        }

        return $rows;
    }
}
