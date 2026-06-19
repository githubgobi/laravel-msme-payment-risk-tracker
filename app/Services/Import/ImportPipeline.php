<?php

namespace App\Services\Import;

use App\DTOs\ImportRow;
use App\DTOs\RowImportResult;
use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\InvoiceStatus;
use App\Models\ImportBatch;
use App\Models\PurchaseInvoice;
use App\Services\InvoiceRiskRecomputer;
use App\Services\MsmeDeadlineEngine;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the full import pipeline for a single ImportBatch.
 *
 * Responsibilities:
 *   1. Select the right parser (CSV vs Tally XML)
 *   2. Validate each row
 *   3. Find/create vendor via VendorMatcher
 *   4. Create PurchaseInvoice record
 *   5. Trigger risk recompute on the new invoice
 *   6. Update ImportBatch with progress and error log
 *
 * Designed to run in a queue job (no auth context — uses batch.created_by
 * as the acting user ID for audit columns).
 */
final class ImportPipeline
{
    private const SAVE_PROGRESS_EVERY = 100;
    private const MAX_ERROR_LOG_SIZE  = 500;

    public function __construct(
        private readonly CsvImporter          $csvImporter,
        private readonly TallyXmlImporter     $tallyXmlImporter,
        private readonly RowValidator         $rowValidator,
        private readonly VendorMatcher        $vendorMatcher,
        private readonly MsmeDeadlineEngine   $engine,
        private readonly InvoiceRiskRecomputer $riskRecomputer,
    ) {}

    /**
     * Run the full pipeline for the given batch.
     * Updates batch.status throughout execution.
     */
    public function process(ImportBatch $batch): void
    {
        $batch->withoutGlobalScopes()->where('id', $batch->id)->update([
            'status'     => ImportStatus::Processing->value,
            'started_at' => now(),
        ]);

        $batch->refresh();

        try {
            $rows = $this->parseFile($batch);
        } catch (Throwable $e) {
            $this->markFailed($batch, "Could not parse file: {$e->getMessage()}");
            return;
        }

        $batch->withoutGlobalScopes()->where('id', $batch->id)->update([
            'total_rows' => $rows->count(),
        ]);

        $this->vendorMatcher->resetCache();

        $tenant   = $batch->tenant;
        $bankRate = (float) ($tenant->rbi_bank_rate ?? MsmeDeadlineEngine::DEFAULT_RBI_BANK_RATE);
        $errors   = [];
        $rowCount = 0;

        // Reload from DB to ensure fresh counts
        $processedRows = 0;
        $skippedRows   = 0;
        $failedRows    = 0;

        foreach ($rows as $row) {
            /** @var ImportRow $row */
            $result = $this->processRow($row, $batch, $bankRate);

            match ($result->status) {
                RowImportResult::STATUS_IMPORTED => $processedRows++,
                RowImportResult::STATUS_SKIPPED  => $skippedRows++,
                RowImportResult::STATUS_FAILED   => $failedRows++,
            };

            if ($result->status !== RowImportResult::STATUS_IMPORTED) {
                if (count($errors) < self::MAX_ERROR_LOG_SIZE) {
                    $errors[] = $result->toArray();
                }
            }

            $rowCount++;

            // Periodically persist progress to show real-time updates
            if ($rowCount % self::SAVE_PROGRESS_EVERY === 0) {
                $batch->withoutGlobalScopes()->where('id', $batch->id)->update([
                    'processed_rows' => $processedRows,
                    'skipped_rows'   => $skippedRows,
                    'failed_rows'    => $failedRows,
                ]);
            }
        }

        $batch->withoutGlobalScopes()->where('id', $batch->id)->update([
            'status'         => ImportStatus::Completed->value,
            'processed_rows' => $processedRows,
            'skipped_rows'   => $skippedRows,
            'failed_rows'    => $failedRows,
            'error_log'      => ! empty($errors) ? json_encode($errors) : null,
            'completed_at'   => now(),
        ]);

        Log::info('Import batch completed', [
            'batch_id'  => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'total'     => $rows->count(),
            'imported'  => $processedRows,
            'skipped'   => $skippedRows,
            'failed'    => $failedRows,
        ]);
    }

    private function processRow(ImportRow $row, ImportBatch $batch, float $bankRate): RowImportResult
    {
        // 1. Validate
        $validationErrors = $this->rowValidator->validate($row);
        if (! empty($validationErrors)) {
            return RowImportResult::failed(
                $row->rowNumber,
                $row->invoiceNumber,
                implode('; ', $validationErrors),
            );
        }

        // 2. Parse validated values
        $invoiceDate     = $this->rowValidator->parseDate($row->invoiceDate);
        $amount          = $this->rowValidator->parseAmount($row->amount);
        $paidAmount      = $this->rowValidator->parseAmount($row->paidAmount) ?? 0.0;
        $agreementExists = $this->rowValidator->parseAgreementExists($row->agreementExists);

        // 3. Find or create vendor
        try {
            $vendor = $this->vendorMatcher->findOrCreate(
                vendorName:  $row->vendorName,
                tenantId:    $batch->tenant_id,
                createdBy:   $batch->created_by,
                gstin:       $row->gstin,
                udyamNumber: $row->udyamNumber,
            );
        } catch (Throwable $e) {
            return RowImportResult::failed(
                $row->rowNumber,
                $row->invoiceNumber,
                "Vendor lookup failed: {$e->getMessage()}",
            );
        }

        // 4. Compute derived fields
        $effectiveDeadline = $this->engine->computeDeadline($invoiceDate, $agreementExists);
        $financialYear     = $this->engine->computeFinancialYear($invoiceDate);

        // 5. Create invoice (duplicate = skip, not fail)
        try {
            $invoice = PurchaseInvoice::withoutGlobalScopes()->create([
                'tenant_id'                => $batch->tenant_id,
                'vendor_id'                => $vendor->id,
                'import_batch_id'          => $batch->id,
                'invoice_number'           => trim($row->invoiceNumber),
                'invoice_date'             => $invoiceDate->toDateString(),
                'amount'                   => $amount,
                'paid_amount'              => $paidAmount,
                'currency'                 => 'INR',
                'agreement_exists'         => $agreementExists,
                'effective_deadline'       => $effectiveDeadline->toDateString(),
                'vendor_category_snapshot' => $vendor->category->value,
                'financial_year'           => $financialYear,
                'status'                   => InvoiceStatus::Pending->value,
                'disallowance_amount'      => 0,
                'interest_amount'          => 0,
                'narration'                => trim($row->narration) ?: null,
                'created_by'               => $batch->created_by,
                'updated_by'               => $batch->created_by,
            ]);
        } catch (QueryException $e) {
            // MySQL error 1062 = duplicate entry
            if ($e->getCode() === '23000') {
                return RowImportResult::skipped(
                    $row->rowNumber,
                    $row->invoiceNumber,
                    'Duplicate invoice — already exists in the system',
                );
            }

            return RowImportResult::failed(
                $row->rowNumber,
                $row->invoiceNumber,
                "Database error: {$e->getMessage()}",
            );
        }

        // 6. Run risk engine on the new invoice immediately
        try {
            $this->riskRecomputer->recomputeOne($invoice, Carbon::today(), $bankRate);
        } catch (Throwable $e) {
            // Non-fatal: invoice is created; risk compute can be rerun via artisan
            Log::warning('Risk recompute failed after import', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return RowImportResult::imported($row->rowNumber, $row->invoiceNumber);
    }

    private function parseFile(ImportBatch $batch): \Illuminate\Support\Collection
    {
        $path = storage_path('app/' . $batch->stored_path);

        return match ($batch->source) {
            ImportSource::TallyXml => $this->tallyXmlImporter->parse($path),
            default                => $this->csvImporter->parse($path),
        };
    }

    private function markFailed(ImportBatch $batch, string $reason): void
    {
        $batch->withoutGlobalScopes()->where('id', $batch->id)->update([
            'status'       => ImportStatus::Failed->value,
            'error_log'    => json_encode([['status' => 'failed', 'row' => 0, 'invoice_number' => '', 'message' => $reason]]),
            'completed_at' => now(),
        ]);

        Log::error('Import batch failed', [
            'batch_id' => $batch->id,
            'reason'   => $reason,
        ]);
    }
}
