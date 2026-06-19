<?php

namespace App\Services;

use App\DTOs\RiskAssessment;
use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use Carbon\Carbon;

/**
 * Pure calculation engine for Section 43B(h) MSME payment compliance.
 *
 * No database access. No Eloquent models. All inputs are primitives.
 * This makes the engine fully unit-testable and framework-independent.
 *
 * Formula reference:
 *   - Deadline:  invoice_date + 15 days (no agreement) | + 45 days (with agreement)
 *   - Interest:  P × ((1 + r)^n − 1)
 *     where r = (bank_rate × 3) / 12 / 100
 *           n = complete months elapsed since deadline
 */
final class MsmeDeadlineEngine
{
    public const INTEREST_MULTIPLIER    = 3;
    public const NO_AGREEMENT_DAYS      = 15;
    public const AGREEMENT_DAYS         = 45;
    public const DEFAULT_RBI_BANK_RATE  = 6.75;

    /**
     * Compute the effective payment deadline for an invoice.
     */
    public function computeDeadline(Carbon $invoiceDate, bool $agreementExists): Carbon
    {
        $days = $agreementExists ? self::AGREEMENT_DAYS : self::NO_AGREEMENT_DAYS;

        return $invoiceDate->copy()->addDays($days);
    }

    /**
     * Compute the Indian financial year string for a given date.
     * India FY: April 1 → March 31.
     * Returns format "2024-25", "2025-26", etc.
     */
    public function computeFinancialYear(Carbon $date): string
    {
        $year  = $date->year;
        $month = $date->month;

        if ($month >= 4) {
            $startYear = $year;
            $endYear   = $year + 1;
        } else {
            $startYear = $year - 1;
            $endYear   = $year;
        }

        return $startYear . '-' . substr((string) $endYear, -2);
    }

    /**
     * Returns the March 31 year-end date for a given financial year string.
     * e.g. "2024-25" → Carbon(2025-03-31)
     */
    public function financialYearEnd(string $financialYear): Carbon
    {
        // "2024-25" → end year is 2025
        $centuryPrefix = (int) substr($financialYear, 0, 2);
        $endYearSuffix = (int) substr($financialYear, 5, 2);
        $endYear       = ($centuryPrefix * 100) + $endYearSuffix;

        return Carbon::create($endYear, 3, 31)->endOfDay();
    }

    /**
     * Count complete months elapsed since deadline up to asOf date.
     * Returns 0 if asOf is on or before deadline.
     */
    public function computeMonthsOverdue(Carbon $deadline, Carbon $asOf): int
    {
        if ($asOf->lte($deadline)) {
            return 0;
        }

        return (int) $deadline->diffInMonths($asOf);
    }

    /**
     * Count calendar days overdue. Returns 0 if not overdue.
     */
    public function computeDaysOverdue(Carbon $deadline, Carbon $asOf): int
    {
        if ($asOf->lte($deadline)) {
            return 0;
        }

        return (int) $deadline->diffInDays($asOf);
    }

    /**
     * Compute compound interest with monthly rests at 3× RBI bank rate.
     *
     * Formula: I = P × ((1 + r)^n − 1)
     *   r = (bankRate × INTEREST_MULTIPLIER) / 12 / 100
     *   n = complete months overdue
     *
     * Returns 0.0 if principal or months is zero.
     */
    public function computeInterest(float $principal, int $months, float $bankRate): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }

        $annualRate  = $bankRate * self::INTEREST_MULTIPLIER;
        $monthlyRate = $annualRate / 12 / 100;
        $interest    = $principal * (pow(1 + $monthlyRate, $months) - 1);

        return round($interest, 2);
    }

    /**
     * Full risk assessment for a single invoice.
     *
     * All inputs are primitives — no Eloquent models allowed here.
     * This is the single source of truth for all 43B(h) calculations.
     */
    public function assess(
        Carbon         $invoiceDate,
        float          $amount,
        float          $paidAmount,
        bool           $agreementExists,
        VendorCategory $vendorCategory,
        float          $bankRate = self::DEFAULT_RBI_BANK_RATE,
        ?Carbon        $asOf = null,
        ?string        $financialYear = null,
    ): RiskAssessment {
        $asOf          ??= Carbon::today();
        $effectiveDeadline = $this->computeDeadline($invoiceDate, $agreementExists);
        $financialYear ??= $this->computeFinancialYear($invoiceDate);
        $fyEnd             = $this->financialYearEnd($financialYear);

        $balance           = round($amount - $paidAmount, 2);
        $isPaid            = $balance <= 0;
        $isSubjectTo43Bh   = $vendorCategory->isSubjectTo43Bh();

        // Negative invoices (credit notes) and fully paid invoices are safe
        if ($isPaid || $amount <= 0) {
            return new RiskAssessment(
                isSubjectTo43Bh:    $isSubjectTo43Bh,
                disallowanceAmount: 0.0,
                interestAmount:     0.0,
                totalTaxExposure:   0.0,
                daysOverdue:        0,
                monthsOverdue:      0,
                isOverdue:          false,
                isPaid:             true,
                financialYear:      $financialYear,
                status:             InvoiceStatus::Paid,
            );
        }

        $daysOverdue   = $this->computeDaysOverdue($effectiveDeadline, $asOf);
        $monthsOverdue = $this->computeMonthsOverdue($effectiveDeadline, $asOf);
        $isOverdue     = $daysOverdue > 0;

        // Not yet due
        if (! $isOverdue) {
            $status = $paidAmount > 0 ? InvoiceStatus::Partial : InvoiceStatus::Pending;

            return new RiskAssessment(
                isSubjectTo43Bh:    $isSubjectTo43Bh,
                disallowanceAmount: 0.0,
                interestAmount:     0.0,
                totalTaxExposure:   0.0,
                daysOverdue:        0,
                monthsOverdue:      0,
                isOverdue:          false,
                isPaid:             false,
                financialYear:      $financialYear,
                status:             $status,
            );
        }

        // Overdue — but 43B(h) only applies to Micro/Small vendors
        if (! $isSubjectTo43Bh) {
            $status = $asOf->gt($fyEnd) ? InvoiceStatus::Overdue : InvoiceStatus::Overdue;

            return new RiskAssessment(
                isSubjectTo43Bh:    false,
                disallowanceAmount: 0.0,
                interestAmount:     0.0,
                totalTaxExposure:   0.0,
                daysOverdue:        $daysOverdue,
                monthsOverdue:      $monthsOverdue,
                isOverdue:          true,
                isPaid:             false,
                financialYear:      $financialYear,
                status:             InvoiceStatus::Overdue,
            );
        }

        // 43B(h) applies — compute disallowance and interest
        $disallowanceAmount = round($balance, 2);
        $interestAmount     = $this->computeInterest($disallowanceAmount, $monthsOverdue, $bankRate);
        $totalTaxExposure   = round($disallowanceAmount + $interestAmount, 2);

        $status = $asOf->gt($fyEnd)
            ? InvoiceStatus::Disallowed
            : InvoiceStatus::Overdue;

        return new RiskAssessment(
            isSubjectTo43Bh:    true,
            disallowanceAmount: $disallowanceAmount,
            interestAmount:     $interestAmount,
            totalTaxExposure:   $totalTaxExposure,
            daysOverdue:        $daysOverdue,
            monthsOverdue:      $monthsOverdue,
            isOverdue:          true,
            isPaid:             false,
            financialYear:      $financialYear,
            status:             $status,
        );
    }
}
