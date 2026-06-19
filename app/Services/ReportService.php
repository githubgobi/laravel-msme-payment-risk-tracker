<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates Section 43B(h) disallowance and interest data per financial year.
 *
 * A financial year in India runs from 1 April to 31 March.
 * For the purposes of 43B(h), we flag invoices where payment was NOT made
 * within the statutory deadline (15 days for micro/small without agreement,
 * 45 days with agreement).  The sum of outstanding amounts is the disallowance
 * quantum.  Interest is computed at 3× RBI bank rate, compounded monthly.
 */
class ReportService
{
    public function annualSummary(Tenant $tenant, int $year): array
    {
        [$fyStart, $fyEnd] = $this->fyBounds($year);

        $invoices = PurchaseInvoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('vendor')
            ->whereBetween('invoice_date', [$fyStart, $fyEnd])
            ->orderBy('invoice_date')
            ->get();

        $totalInvoiceAmount  = (float) $invoices->sum('amount');
        $totalPaid           = (float) $invoices->sum('paid_amount');
        $totalOutstanding    = $invoices->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount);
        $overdueInvoices     = $invoices->filter(fn ($i) => $i->status === InvoiceStatus::Overdue);
        $disallowanceAmount  = $overdueInvoices->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount);

        // Aggregate per vendor
        $vendorRows = $invoices
            ->groupBy('vendor_id')
            ->map(function (Collection $group) use ($tenant): array {
                $vendor    = $group->first()->vendor;
                $overdue   = $group->filter(fn ($i) => $i->status === InvoiceStatus::Overdue);
                $interest  = $overdue->sum(fn ($i) => $this->computeInterest($i, $tenant->rbi_bank_rate));

                return [
                    'vendor_name'          => $vendor->name,
                    'vendor_gstin'         => $vendor->gstin ?? null,
                    'category'             => $vendor->category->value ?? '—',
                    'invoice_count'        => $group->count(),
                    'total_amount'         => (float) $group->sum('amount'),
                    'paid_amount'          => (float) $group->sum('paid_amount'),
                    'outstanding_amount'   => $group->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount),
                    'overdue_invoices'     => $overdue->count(),
                    'disallowance_amount'  => $overdue->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount),
                    'interest_payable'     => round($interest, 2),
                ];
            })
            ->values()
            ->toArray();

        $totalInterest = array_sum(array_column($vendorRows, 'interest_payable'));

        return [
            'tenant_name'          => $tenant->name,
            'tenant_gstin'         => $tenant->gstin,
            'financial_year'       => "FY {$year}-" . ($year + 1),
            'fy_start'             => $fyStart->toDateString(),
            'fy_end'               => $fyEnd->toDateString(),
            'rbi_bank_rate'        => (float) $tenant->rbi_bank_rate,
            'applicable_rate'      => (float) $tenant->rbi_bank_rate * 3,
            'total_invoice_amount' => (float) $totalInvoiceAmount,
            'total_paid'           => (float) $totalPaid,
            'total_outstanding'    => (float) $totalOutstanding,
            'disallowance_amount'  => (float) $disallowanceAmount,
            'total_interest'       => round($totalInterest, 2),
            'vendor_rows'          => $vendorRows,
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    /**
     * Compute interest at 3× RBI bank rate compounded monthly on a single invoice.
     */
    public function computeInterest(PurchaseInvoice $invoice, float $rbiBankRate): float
    {
        if ($invoice->status !== InvoiceStatus::Overdue) {
            return 0.0;
        }

        $outstanding = (float) $invoice->amount - (float) $invoice->paid_amount;
        if ($outstanding <= 0) {
            return 0.0;
        }

        $annualRate  = $rbiBankRate * 3 / 100;   // 3× RBI bank rate per annum
        $monthlyRate = $annualRate / 12;

        $overdueFrom = $invoice->effective_deadline ?? $invoice->invoice_date;
        $months      = (int) ceil(Carbon::parse($overdueFrom)->diffInDays(now()) / 30);
        $months      = max(1, $months);

        // Compound monthly: A = P × (1 + r)^n - P
        return $outstanding * (pow(1 + $monthlyRate, $months) - 1);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function fyBounds(int $year): array
    {
        return [
            Carbon::create($year, 4, 1)->startOfDay(),
            Carbon::create($year + 1, 3, 31)->endOfDay(),
        ];
    }
}
