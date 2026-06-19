<?php

namespace Tests\Unit\Services;

use App\DTOs\RiskAssessment;
use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use App\Services\MsmeDeadlineEngine;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MsmeDeadlineEngineTest extends TestCase
{
    private MsmeDeadlineEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MsmeDeadlineEngine();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // computeDeadline
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function compute_deadline_adds_15_days_when_no_agreement(): void
    {
        $invoiceDate = Carbon::parse('2025-01-15');
        $deadline    = $this->engine->computeDeadline($invoiceDate, false);

        $this->assertTrue($deadline->isSameDay(Carbon::parse('2025-01-30')));
    }

    #[Test]
    public function compute_deadline_adds_45_days_when_agreement_exists(): void
    {
        $invoiceDate = Carbon::parse('2025-01-15');
        $deadline    = $this->engine->computeDeadline($invoiceDate, true);

        $this->assertTrue($deadline->isSameDay(Carbon::parse('2025-03-01')));
    }

    #[Test]
    public function compute_deadline_crosses_month_boundary_correctly(): void
    {
        // Jan 31 + 15 days = Feb 15
        $deadline = $this->engine->computeDeadline(Carbon::parse('2025-01-31'), false);
        $this->assertTrue($deadline->isSameDay(Carbon::parse('2025-02-15')));
    }

    #[Test]
    public function compute_deadline_handles_leap_year(): void
    {
        // Feb 14 2024 (leap year) + 15 = Feb 29 2024
        $deadline = $this->engine->computeDeadline(Carbon::parse('2024-02-14'), false);
        $this->assertTrue($deadline->isSameDay(Carbon::parse('2024-02-29')));
    }

    #[Test]
    public function compute_deadline_handles_non_leap_year(): void
    {
        // Feb 14 2025 (non-leap) + 15 = Mar 1 2025
        $deadline = $this->engine->computeDeadline(Carbon::parse('2025-02-14'), false);
        $this->assertTrue($deadline->isSameDay(Carbon::parse('2025-03-01')));
    }

    #[Test]
    public function compute_deadline_does_not_mutate_original_date(): void
    {
        $original = Carbon::parse('2025-01-15');
        $original->copy(); // sanity marker
        $this->engine->computeDeadline($original, false);

        $this->assertEquals('2025-01-15', $original->toDateString());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // computeFinancialYear
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function compute_fy_on_april_1_starts_new_fy(): void
    {
        $this->assertSame('2025-26', $this->engine->computeFinancialYear(Carbon::parse('2025-04-01')));
    }

    #[Test]
    public function compute_fy_on_march_31_stays_in_old_fy(): void
    {
        $this->assertSame('2024-25', $this->engine->computeFinancialYear(Carbon::parse('2025-03-31')));
    }

    #[Test]
    public function compute_fy_mid_year_january(): void
    {
        $this->assertSame('2024-25', $this->engine->computeFinancialYear(Carbon::parse('2025-01-15')));
    }

    #[Test]
    public function compute_fy_mid_year_october(): void
    {
        $this->assertSame('2025-26', $this->engine->computeFinancialYear(Carbon::parse('2025-10-20')));
    }

    #[Test]
    public function compute_fy_on_march_31_2024(): void
    {
        $this->assertSame('2023-24', $this->engine->computeFinancialYear(Carbon::parse('2024-03-31')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // financialYearEnd
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function fy_end_returns_march_31_of_end_year(): void
    {
        $fyEnd = $this->engine->financialYearEnd('2024-25');
        $this->assertSame('2025-03-31', $fyEnd->toDateString());
    }

    #[Test]
    public function fy_end_for_2025_26_returns_march_31_2026(): void
    {
        $fyEnd = $this->engine->financialYearEnd('2025-26');
        $this->assertSame('2026-03-31', $fyEnd->toDateString());
    }

    #[Test]
    public function fy_end_for_2023_24(): void
    {
        $fyEnd = $this->engine->financialYearEnd('2023-24');
        $this->assertSame('2024-03-31', $fyEnd->toDateString());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // computeMonthsOverdue
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function months_overdue_returns_zero_when_not_overdue(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $this->assertSame(0, $this->engine->computeMonthsOverdue($deadline, Carbon::parse('2025-01-30')));
        $this->assertSame(0, $this->engine->computeMonthsOverdue($deadline, Carbon::parse('2025-01-15')));
    }

    #[Test]
    public function months_overdue_returns_zero_for_partial_month(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $asOf     = Carbon::parse('2025-02-28'); // 29 days after — < 1 complete month
        $this->assertSame(0, $this->engine->computeMonthsOverdue($deadline, $asOf));
    }

    #[Test]
    public function months_overdue_returns_one_at_exactly_one_month(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $asOf     = Carbon::parse('2025-02-28'); // Feb 28; 1 month from Jan 30 in Carbon? Let's use Mar 1
        // Jan 30 + 1 month = Feb 28 (Carbon adds 1 month)
        $asOfExact = Carbon::parse('2025-03-01'); // definitely 1+ month
        $this->assertSame(1, $this->engine->computeMonthsOverdue($deadline, $asOfExact));
    }

    #[Test]
    public function months_overdue_returns_six_at_six_months(): void
    {
        $deadline = Carbon::parse('2024-06-16');
        $asOf     = Carbon::parse('2024-12-16'); // exactly 6 months
        $this->assertSame(6, $this->engine->computeMonthsOverdue($deadline, $asOf));
    }

    #[Test]
    public function months_overdue_returns_eleven_after_eleven_months(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $asOf     = Carbon::parse('2025-12-30'); // exactly 11 months
        $this->assertSame(11, $this->engine->computeMonthsOverdue($deadline, $asOf));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // computeDaysOverdue
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function days_overdue_returns_zero_on_deadline_date(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $this->assertSame(0, $this->engine->computeDaysOverdue($deadline, Carbon::parse('2025-01-30')));
    }

    #[Test]
    public function days_overdue_returns_zero_before_deadline(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $this->assertSame(0, $this->engine->computeDaysOverdue($deadline, Carbon::parse('2025-01-15')));
    }

    #[Test]
    public function days_overdue_returns_one_day_after_deadline(): void
    {
        $deadline = Carbon::parse('2025-01-30');
        $this->assertSame(1, $this->engine->computeDaysOverdue($deadline, Carbon::parse('2025-01-31')));
    }

    #[Test]
    public function days_overdue_returns_correct_count_across_months(): void
    {
        $deadline = Carbon::parse('2025-01-16');
        $asOf     = Carbon::parse('2025-02-01'); // 16 days later
        $this->assertSame(16, $this->engine->computeDaysOverdue($deadline, $asOf));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // computeInterest
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function interest_is_zero_for_zero_principal(): void
    {
        $this->assertSame(0.0, $this->engine->computeInterest(0.0, 6, 6.75));
    }

    #[Test]
    public function interest_is_zero_for_negative_principal(): void
    {
        $this->assertSame(0.0, $this->engine->computeInterest(-1000.0, 6, 6.75));
    }

    #[Test]
    public function interest_is_zero_for_zero_months(): void
    {
        $this->assertSame(0.0, $this->engine->computeInterest(100000.0, 0, 6.75));
    }

    #[Test]
    public function interest_for_one_month_equals_monthly_rate_times_principal(): void
    {
        // 1 month: I = P * r, where r = (6.75*3)/12/100 = 0.016875
        $expected = round(100000.0 * 0.016875, 2); // 1687.50
        $actual   = $this->engine->computeInterest(100000.0, 1, 6.75);

        $this->assertEqualsWithDelta($expected, $actual, 0.01);
    }

    #[Test]
    public function interest_compounds_correctly_over_six_months(): void
    {
        // P=100000, bank_rate=6.75, months=6
        // monthly_rate = (6.75*3)/12/100 = 0.016875
        // I = 100000 * ((1.016875)^6 - 1)
        $monthlyRate = (6.75 * 3) / 12 / 100;
        $expected    = round(100000.0 * (pow(1 + $monthlyRate, 6) - 1), 2);
        $actual      = $this->engine->computeInterest(100000.0, 6, 6.75);

        $this->assertEqualsWithDelta($expected, $actual, 0.01);
    }

    #[Test]
    public function interest_compounds_correctly_over_twelve_months(): void
    {
        $monthlyRate = (6.75 * 3) / 12 / 100;
        $expected    = round(500000.0 * (pow(1 + $monthlyRate, 12) - 1), 2);
        $actual      = $this->engine->computeInterest(500000.0, 12, 6.75);

        $this->assertEqualsWithDelta($expected, $actual, 0.01);
    }

    #[Test]
    public function interest_uses_bank_rate_multiplied_by_three(): void
    {
        // Verify that a higher bank rate produces higher interest
        $lowRate  = $this->engine->computeInterest(100000.0, 6, 5.0);
        $highRate = $this->engine->computeInterest(100000.0, 6, 8.0);

        $this->assertLessThan($highRate, $lowRate);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // assess() — integration scenarios
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function assess_fully_paid_invoice_returns_paid_status(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          500000.0,
            paidAmount:      500000.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $this->assertTrue($result->isPaid);
        $this->assertSame(InvoiceStatus::Paid, $result->status);
        $this->assertSame(0.0, $result->disallowanceAmount);
        $this->assertSame(0.0, $result->interestAmount);
    }

    #[Test]
    public function assess_credit_note_negative_amount_returns_paid_status(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          -50000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $this->assertTrue($result->isPaid);
        $this->assertSame(InvoiceStatus::Paid, $result->status);
    }

    #[Test]
    public function assess_pending_invoice_before_deadline_returns_pending_status(): void
    {
        // Invoice June 18 2025, no agreement → deadline July 3 2025
        // asOf June 19 2025 (before deadline)
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-06-18'),
            amount:          300000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-06-19'),
        );

        $this->assertSame(InvoiceStatus::Pending, $result->status);
        $this->assertFalse($result->isOverdue);
        $this->assertSame(0.0, $result->disallowanceAmount);
    }

    #[Test]
    public function assess_partially_paid_invoice_before_deadline_returns_partial_status(): void
    {
        // Invoice June 10 2025, no agreement → deadline June 25 2025
        // Partial payment made, asOf June 19 2025 (still before deadline)
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-06-10'),
            amount:          300000.0,
            paidAmount:      100000.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-06-19'),
        );

        $this->assertSame(InvoiceStatus::Partial, $result->status);
        $this->assertFalse($result->isOverdue);
        $this->assertSame(0.0, $result->disallowanceAmount);
    }

    #[Test]
    public function assess_payment_exactly_on_deadline_is_not_overdue(): void
    {
        // Invoice Jan 15 2025, no agreement → deadline Jan 30 2025
        // Pay on Jan 30 — asOf = Jan 30 = deadline → safe
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-01-30'),
        );

        $this->assertFalse($result->isOverdue);
        $this->assertSame(0.0, $result->disallowanceAmount);
        $this->assertSame(InvoiceStatus::Pending, $result->status);
    }

    #[Test]
    public function assess_one_day_after_deadline_marks_overdue(): void
    {
        // Invoice Jan 15 2025, no agreement → deadline Jan 30 2025
        // asOf = Jan 31 2025 (1 day after deadline)
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-01-31'),
        );

        $this->assertTrue($result->isOverdue);
        $this->assertTrue($result->isSubjectTo43Bh);
        $this->assertSame(1, $result->daysOverdue);
        $this->assertSame(0, $result->monthsOverdue); // < 1 complete month
        $this->assertSame(100000.0, $result->disallowanceAmount);
        $this->assertSame(0.0, $result->interestAmount); // no complete months yet
        $this->assertSame(InvoiceStatus::Overdue, $result->status);
    }

    #[Test]
    public function assess_micro_vendor_overdue_six_months_within_fy(): void
    {
        // Invoice May 1 2024, no agreement → deadline May 16 2024 (FY 2024-25)
        // asOf Nov 16 2024 — exactly 6 months overdue, FY not yet ended (ends March 31 2025)
        $monthlyRate = (6.75 * 3) / 12 / 100;
        $expectedInterest = round(100000.0 * (pow(1 + $monthlyRate, 6) - 1), 2);

        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2024-05-01'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            bankRate:        6.75,
            asOf:            Carbon::parse('2024-11-16'),
        );

        $this->assertSame(InvoiceStatus::Overdue, $result->status);
        $this->assertSame(6, $result->monthsOverdue);
        $this->assertSame(100000.0, $result->disallowanceAmount);
        $this->assertEqualsWithDelta($expectedInterest, $result->interestAmount, 0.01);
        $this->assertEqualsWithDelta(100000.0 + $expectedInterest, $result->totalTaxExposure, 0.01);
    }

    #[Test]
    public function assess_micro_vendor_past_fy_end_returns_disallowed(): void
    {
        // Same setup but asOf = April 2 2025 (FY 2024-25 ended March 31 2025)
        // Deadline was May 16 2024, months from May 16 2024 to Apr 2 2025:
        // May 16 → Dec 16 = 7 months, Dec 16 → Apr 2 is between 3-4 months → 10 complete months
        $deadline     = Carbon::parse('2024-05-16');
        $asOf         = Carbon::parse('2025-04-02');
        $monthsActual = (int) $deadline->diffInMonths($asOf); // 10

        $monthlyRate      = (6.75 * 3) / 12 / 100;
        $expectedInterest = round(100000.0 * (pow(1 + $monthlyRate, $monthsActual) - 1), 2);

        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2024-05-01'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            bankRate:        6.75,
            asOf:            $asOf,
        );

        $this->assertSame(InvoiceStatus::Disallowed, $result->status);
        $this->assertSame(100000.0, $result->disallowanceAmount);
        $this->assertEqualsWithDelta($expectedInterest, $result->interestAmount, 0.01);
    }

    #[Test]
    public function assess_partial_payment_before_deadline_balance_is_at_risk(): void
    {
        // Invoice Jan 1 2025, with agreement → deadline Feb 15 2025
        // Paid ₹50,000 before deadline. Remaining ₹1,50,000 unpaid.
        // asOf March 15 2025 (1 month after Feb 15 deadline)
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-01'),
            amount:          200000.0,
            paidAmount:      50000.0,
            agreementExists: true,
            vendorCategory:  VendorCategory::Small,
            bankRate:        6.75,
            asOf:            Carbon::parse('2025-03-15'),
        );

        $monthlyRate     = (6.75 * 3) / 12 / 100;
        $expectedInterest = round(150000.0 * (pow(1 + $monthlyRate, 1) - 1), 2);

        $this->assertTrue($result->isOverdue);
        $this->assertSame(150000.0, $result->disallowanceAmount); // only balance at risk
        $this->assertEqualsWithDelta($expectedInterest, $result->interestAmount, 0.01);
    }

    #[Test]
    public function assess_medium_vendor_overdue_is_not_subject_to_43bh(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-01'),
            amount:          1000000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Medium,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $this->assertFalse($result->isSubjectTo43Bh);
        $this->assertSame(0.0, $result->disallowanceAmount);
        $this->assertSame(0.0, $result->interestAmount);
        $this->assertSame(0.0, $result->totalTaxExposure);
        // Still shows overdue status because the invoice IS overdue, just not 43B(h) relevant
        $this->assertSame(InvoiceStatus::Overdue, $result->status);
    }

    #[Test]
    public function assess_large_vendor_overdue_is_not_subject_to_43bh(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-01'),
            amount:          5000000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Large,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $this->assertFalse($result->isSubjectTo43Bh);
        $this->assertSame(0.0, $result->disallowanceAmount);
    }

    #[Test]
    public function assess_unclassified_vendor_is_not_subject_to_43bh(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-01'),
            amount:          200000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Unclassified,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $this->assertFalse($result->isSubjectTo43Bh);
        $this->assertSame(0.0, $result->disallowanceAmount);
    }

    #[Test]
    public function assess_with_agreement_uses_45_day_deadline(): void
    {
        // Invoice Feb 1 2025, with agreement → deadline March 18 2025
        // asOf March 17 2025 (one day before deadline)
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-02-01'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: true,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-03-17'),
        );

        $this->assertFalse($result->isOverdue);
        $this->assertSame(InvoiceStatus::Pending, $result->status);
    }

    #[Test]
    public function assess_returns_correct_financial_year(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2025-06-19'),
        );

        $this->assertSame('2024-25', $result->financialYear);
    }

    #[Test]
    public function assess_accepts_overridden_financial_year(): void
    {
        // FY override: even though invoice is in 2025, force "2025-26"
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2026-04-01'), // after FY 2025-26 ends
            financialYear:   '2025-26',
        );

        $this->assertSame('2025-26', $result->financialYear);
        $this->assertSame(InvoiceStatus::Disallowed, $result->status);
    }

    #[Test]
    public function assess_to_array_contains_all_required_keys(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2025-01-15'),
            amount:          100000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            asOf:            Carbon::parse('2026-06-19'),
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('is_subject_to_43bh', $array);
        $this->assertArrayHasKey('disallowance_amount', $array);
        $this->assertArrayHasKey('interest_amount', $array);
        $this->assertArrayHasKey('total_tax_exposure', $array);
        $this->assertArrayHasKey('days_overdue', $array);
        $this->assertArrayHasKey('months_overdue', $array);
        $this->assertArrayHasKey('is_overdue', $array);
        $this->assertArrayHasKey('is_paid', $array);
        $this->assertArrayHasKey('financial_year', $array);
        $this->assertArrayHasKey('status', $array);
    }

    #[Test]
    public function assess_total_tax_exposure_equals_disallowance_plus_interest(): void
    {
        $result = $this->engine->assess(
            invoiceDate:     Carbon::parse('2024-05-01'),
            amount:          500000.0,
            paidAmount:      0.0,
            agreementExists: false,
            vendorCategory:  VendorCategory::Micro,
            bankRate:        6.75,
            asOf:            Carbon::parse('2024-11-16'),
        );

        $this->assertEqualsWithDelta(
            $result->disallowanceAmount + $result->interestAmount,
            $result->totalTaxExposure,
            0.01
        );
    }

    #[Test]
    public function risk_assessment_safe_factory_returns_no_risk(): void
    {
        $safe = RiskAssessment::safe('2024-25');

        $this->assertFalse($safe->isSubjectTo43Bh);
        $this->assertSame(0.0, $safe->disallowanceAmount);
        $this->assertSame(0.0, $safe->interestAmount);
        $this->assertSame(0.0, $safe->totalTaxExposure);
        $this->assertSame(0, $safe->daysOverdue);
        $this->assertSame(0, $safe->monthsOverdue);
        $this->assertFalse($safe->isOverdue);
        $this->assertFalse($safe->isPaid);
        $this->assertSame('2024-25', $safe->financialYear);
        $this->assertSame(InvoiceStatus::Pending, $safe->status);
    }
}
