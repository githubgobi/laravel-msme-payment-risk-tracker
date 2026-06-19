<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Tenant $tenant;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportService();

        $this->tenant = Tenant::factory()->create([
            'rbi_bank_rate'       => 6.75,
            'subscription_status' => TenantStatus::Active->value,
        ]);

        $this->vendor = Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function annual_summary_returns_correct_structure(): void
    {
        $summary = $this->service->annualSummary($this->tenant, 2025);

        $this->assertArrayHasKey('financial_year', $summary);
        $this->assertArrayHasKey('vendor_rows', $summary);
        $this->assertArrayHasKey('disallowance_amount', $summary);
        $this->assertArrayHasKey('total_interest', $summary);
        $this->assertEquals('FY 2025-2026', $summary['financial_year']);
    }

    #[Test]
    public function annual_summary_includes_overdue_invoices_in_disallowance(): void
    {
        PurchaseInvoice::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'vendor_id'          => $this->vendor->id,
            'invoice_date'       => '2025-06-01',
            'amount'             => 200000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Overdue->value,
            'effective_deadline' => '2025-06-16',
        ]);

        $summary = $this->service->annualSummary($this->tenant, 2025);

        $this->assertEquals(200000.0, $summary['disallowance_amount']);
    }

    #[Test]
    public function annual_summary_excludes_paid_invoices_from_disallowance(): void
    {
        PurchaseInvoice::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vendor_id'    => $this->vendor->id,
            'invoice_date' => '2025-06-01',
            'amount'       => 100000,
            'paid_amount'  => 100000,
            'status'       => InvoiceStatus::Paid->value,
        ]);

        $summary = $this->service->annualSummary($this->tenant, 2025);

        $this->assertEquals(0.0, $summary['disallowance_amount']);
    }

    #[Test]
    public function annual_summary_only_includes_invoices_within_fy(): void
    {
        // Invoice in FY 2024-25 (should NOT appear in FY 2025-26 report)
        PurchaseInvoice::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vendor_id'    => $this->vendor->id,
            'invoice_date' => '2024-12-01',
            'amount'       => 150000,
            'paid_amount'  => 0,
            'status'       => InvoiceStatus::Overdue->value,
        ]);

        $summary = $this->service->annualSummary($this->tenant, 2025);

        $this->assertEquals(0.0, $summary['disallowance_amount']);
    }

    #[Test]
    public function compute_interest_returns_zero_for_non_overdue_invoices(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'vendor_id'  => $this->vendor->id,
            'amount'     => 100000,
            'paid_amount' => 0,
            'status'     => InvoiceStatus::Pending->value,
        ]);

        $interest = $this->service->computeInterest($invoice, 6.75);

        $this->assertEquals(0.0, $interest);
    }

    #[Test]
    public function compute_interest_returns_positive_for_overdue_invoice(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'vendor_id'          => $this->vendor->id,
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Overdue->value,
            'effective_deadline' => Carbon::now()->subDays(60)->toDateString(),
        ]);

        $interest = $this->service->computeInterest($invoice, 6.75);

        $this->assertGreaterThan(0, $interest);
    }

    #[Test]
    public function compute_interest_is_zero_when_fully_paid(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'vendor_id'   => $this->vendor->id,
            'amount'      => 100000,
            'paid_amount' => 100000,
            'status'      => InvoiceStatus::Overdue->value,
        ]);

        $interest = $this->service->computeInterest($invoice, 6.75);

        $this->assertEquals(0.0, $interest);
    }

    #[Test]
    public function vendor_rows_aggregate_correctly(): void
    {
        PurchaseInvoice::factory()->count(3)->create([
            'tenant_id'    => $this->tenant->id,
            'vendor_id'    => $this->vendor->id,
            'invoice_date' => '2025-05-01',
            'amount'       => 100000,
            'paid_amount'  => 0,
            'status'       => InvoiceStatus::Overdue->value,
        ]);

        $summary = $this->service->annualSummary($this->tenant, 2025);

        $this->assertCount(1, $summary['vendor_rows']);
        $this->assertEquals(3, $summary['vendor_rows'][0]['invoice_count']);
        $this->assertEquals(300000.0, $summary['vendor_rows'][0]['total_amount']);
        $this->assertEquals(3, $summary['vendor_rows'][0]['overdue_invoices']);
    }
}
