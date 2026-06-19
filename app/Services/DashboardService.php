<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DashboardService
{
    private const FY_MONTH_ORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
    private const FY_MONTH_LABELS = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

    /**
     * Returns the current Indian financial year string, e.g. "2025-26".
     */
    public function currentFy(): string
    {
        $now = Carbon::today();
        $y   = $now->year;
        $m   = $now->month;

        return $m >= 4
            ? "{$y}-" . substr($y + 1, -2)
            : ($y - 1) . '-' . substr($y, -2);
    }

    /**
     * Returns the FY string from a "YYYY-YY" input, or the current FY if invalid.
     */
    public function resolveFy(?string $input): string
    {
        if ($input && preg_match('/^\d{4}-\d{2}$/', $input)) {
            return $input;
        }

        return $this->currentFy();
    }

    /**
     * Returns distinct financial years that have invoices, newest first.
     * Includes current FY even if no invoices yet.
     */
    public function availableYears(): array
    {
        $existing = PurchaseInvoice::query()
            ->selectRaw('DISTINCT financial_year')
            ->orderByDesc('financial_year')
            ->pluck('financial_year')
            ->toArray();

        $current = $this->currentFy();

        if (! in_array($current, $existing)) {
            array_unshift($existing, $current);
        }

        return $existing;
    }

    /**
     * Aggregated KPI stats for a financial year.
     *
     * @return array{
     *   at_risk_count: int,
     *   total_at_risk: float,
     *   projected_disallowance: float,
     *   projected_interest: float,
     *   overdue_count: int,
     *   due_this_week: int,
     * }
     */
    public function summaryStats(string $fy): array
    {
        $today   = Carbon::today();
        $in7Days = Carbon::today()->addDays(7);

        $atRisk = PurchaseInvoice::atRisk()
            ->forFinancialYear($fy)
            ->selectRaw('
                COUNT(*) as at_risk_count,
                COALESCE(SUM(CASE WHEN vendor_category_snapshot IN (\'micro\', \'small\')
                    THEN (amount - paid_amount) ELSE 0 END), 0) as total_at_risk,
                COALESCE(SUM(disallowance_amount), 0) as projected_disallowance,
                COALESCE(SUM(interest_amount), 0) as projected_interest
            ')
            ->first();

        $overdueCount = PurchaseInvoice::overdue()
            ->forFinancialYear($fy)
            ->count();

        $dueThisWeek = PurchaseInvoice::atRisk()
            ->forFinancialYear($fy)
            ->whereDate('effective_deadline', '>=', $today)
            ->whereDate('effective_deadline', '<=', $in7Days)
            ->count();

        return [
            'at_risk_count'          => (int)   ($atRisk->at_risk_count ?? 0),
            'total_at_risk'          => (float)  ($atRisk->total_at_risk ?? 0),
            'projected_disallowance' => (float)  ($atRisk->projected_disallowance ?? 0),
            'projected_interest'     => (float)  ($atRisk->projected_interest ?? 0),
            'overdue_count'          => $overdueCount,
            'due_this_week'          => $dueThisWeek,
        ];
    }

    /**
     * Top 15 at-risk invoices sorted by urgency:
     *   overdue first → then by effective_deadline ASC.
     */
    public function atRiskInvoices(string $fy, int $limit = 15): Collection
    {
        $today = Carbon::today();

        return PurchaseInvoice::atRisk()
            ->forFinancialYear($fy)
            ->with(['vendor:id,name,category'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'partial' THEN 1 ELSE 2 END")
            ->orderBy('effective_deadline')
            ->limit($limit)
            ->get()
            ->map(function (PurchaseInvoice $inv) use ($today) {
                $daysRemaining = $today->diffInDays($inv->effective_deadline, false);

                return [
                    'id'                => $inv->id,
                    'invoice_number'    => $inv->invoice_number,
                    'vendor_name'       => $inv->vendor?->name ?? '—',
                    'vendor_category'   => $inv->vendor_category_snapshot->label(),
                    'amount'            => (float) $inv->amount,
                    'paid_amount'       => (float) $inv->paid_amount,
                    'balance'           => (float) $inv->amount - (float) $inv->paid_amount,
                    'effective_deadline' => $inv->effective_deadline->format('d M Y'),
                    'days_remaining'    => (int) $daysRemaining,
                    'disallowance_amount' => (float) $inv->disallowance_amount,
                    'interest_amount'   => (float) $inv->interest_amount,
                    'status'            => $inv->status->value,
                    'status_label'      => $inv->status->label(),
                ];
            });
    }

    /**
     * Vendor count breakdown by category, tenant-scoped.
     */
    public function vendorBreakdown(): array
    {
        $counts = Vendor::query()
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category')
            ->toArray();

        return [
            'micro'        => (int) ($counts[VendorCategory::Micro->value]        ?? 0),
            'small'        => (int) ($counts[VendorCategory::Small->value]        ?? 0),
            'medium'       => (int) ($counts[VendorCategory::Medium->value]       ?? 0),
            'large'        => (int) ($counts[VendorCategory::Large->value]        ?? 0),
            'unclassified' => (int) ($counts[VendorCategory::Unclassified->value] ?? 0),
            'total'        => (int) array_sum($counts),
        ];
    }

    /**
     * 12-month disallowance/interest trend for the given FY.
     * Months are in India FY order: Apr → Mar.
     * Computed in PHP (not SQL) to stay database-driver-agnostic.
     */
    public function monthlyTrend(string $fy): array
    {
        $invoices = PurchaseInvoice::forFinancialYear($fy)
            ->select(['invoice_date', 'disallowance_amount', 'interest_amount', 'status'])
            ->get();

        $trend = [];

        foreach (self::FY_MONTH_ORDER as $i => $month) {
            $monthData = $invoices->filter(
                fn(PurchaseInvoice $inv) => $inv->invoice_date->month === $month
            );
            $atRisk = $monthData->filter(fn(PurchaseInvoice $inv) => $inv->status->isAtRisk());

            $trend[] = [
                'month'        => self::FY_MONTH_LABELS[$i],
                'count'        => $monthData->count(),
                'at_risk'      => $atRisk->count(),
                'disallowance' => round((float) $atRisk->sum('disallowance_amount'), 2),
                'interest'     => round((float) $atRisk->sum('interest_amount'), 2),
            ];
        }

        return $trend;
    }

    /**
     * Number of active vendors that are still unclassified.
     * These create blind spots in disallowance calculations.
     */
    public function unclassifiedVendorCount(): int
    {
        return Vendor::where('category', VendorCategory::Unclassified)->count();
    }
}
