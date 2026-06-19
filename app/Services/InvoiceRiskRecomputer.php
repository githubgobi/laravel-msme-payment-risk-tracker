<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * DB-aware batch service that runs MsmeDeadlineEngine across all
 * at-risk invoices for a tenant and persists the computed values.
 *
 * Uses chunk() to avoid loading all invoices into memory.
 */
final class InvoiceRiskRecomputer
{
    public function __construct(
        private readonly MsmeDeadlineEngine $engine,
    ) {}

    /**
     * Recompute risk for all non-paid invoices of a tenant.
     * Returns count of invoices updated.
     */
    public function recomputeForTenant(Tenant $tenant, ?Carbon $asOf = null): int
    {
        $asOf      = $asOf ?? Carbon::today();
        $bankRate  = (float) $tenant->rbi_bank_rate;
        $updated   = 0;

        PurchaseInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', [InvoiceStatus::Paid->value])
            ->whereNull('deleted_at')
            ->with('vendor:id,category')
            ->chunk(200, function ($invoices) use ($asOf, $bankRate, &$updated) {
                foreach ($invoices as $invoice) {
                    $this->recomputeOne($invoice, $asOf, $bankRate);
                    $updated++;
                }
            });

        Log::info('MSME risk recomputed', [
            'tenant_id' => $tenant->id,
            'updated'   => $updated,
            'as_of'     => $asOf->toDateString(),
        ]);

        return $updated;
    }

    /**
     * Recompute and persist risk for a single invoice.
     * Called directly when a payment is recorded.
     */
    public function recomputeOne(PurchaseInvoice $invoice, ?Carbon $asOf = null, ?float $bankRate = null): void
    {
        $asOf     = $asOf ?? Carbon::today();
        $bankRate = $bankRate ?? (float) ($invoice->tenant?->rbi_bank_rate ?? MsmeDeadlineEngine::DEFAULT_RBI_BANK_RATE);

        $vendorCategory = $invoice->vendor_category_snapshot ?? VendorCategory::Unclassified;

        $assessment = $this->engine->assess(
            invoiceDate:    Carbon::parse($invoice->invoice_date),
            amount:         (float) $invoice->amount,
            paidAmount:     (float) $invoice->paid_amount,
            agreementExists: (bool) $invoice->agreement_exists,
            vendorCategory: $vendorCategory,
            bankRate:       $bankRate,
            asOf:           $asOf,
            financialYear:  $invoice->financial_year,
        );

        PurchaseInvoice::withoutGlobalScopes()
            ->where('id', $invoice->id)
            ->update([
                'status'              => $assessment->status->value,
                'disallowance_amount' => $assessment->disallowanceAmount,
                'interest_amount'     => $assessment->interestAmount,
                'last_computed_at'    => now(),
            ]);
    }
}
