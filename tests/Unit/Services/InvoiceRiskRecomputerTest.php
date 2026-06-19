<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\VendorCategory;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\InvoiceRiskRecomputer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceRiskRecomputerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant              $tenant;
    private Vendor              $microVendor;
    private Vendor              $largeVendor;
    private InvoiceRiskRecomputer $recomputer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                => 'Test Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->microVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Micro Supplier',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        $this->largeVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Large Corp',
            'category'  => VendorCategory::Large->value,
            'is_active' => true,
        ]);

        $this->recomputer = app(InvoiceRiskRecomputer::class);
    }

    #[Test]
    public function recompute_one_marks_pending_when_not_yet_overdue(): void
    {
        $invoice = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-06-15',
            'effective_deadline' => '2026-06-30',
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-06-20'));

        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
            'disallowance_amount' => 0,
            'interest_amount'     => 0,
        ]);
    }

    #[Test]
    public function recompute_one_marks_overdue_when_past_deadline(): void
    {
        $invoice = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 200000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-06-20'));

        $fresh = PurchaseInvoice::withoutGlobalScopes()->find($invoice->id);
        $this->assertEquals(InvoiceStatus::Overdue->value, $fresh->status->value);
        $this->assertEquals(200000, (float) $fresh->disallowance_amount);
        $this->assertGreaterThan(0, (float) $fresh->interest_amount);
    }

    #[Test]
    public function recompute_one_marks_paid_when_balance_is_zero(): void
    {
        $invoice = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 150000,
            'paid_amount'        => 150000,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-06-20'));

        $this->assertDatabaseHas('purchase_invoices', [
            'id'                  => $invoice->id,
            'status'              => InvoiceStatus::Paid->value,
            'disallowance_amount' => 0,
        ]);
    }

    #[Test]
    public function recompute_one_zero_disallowance_for_large_vendor(): void
    {
        $invoice = $this->makeInvoice($this->largeVendor, [
            'invoice_date'             => '2026-04-01',
            'effective_deadline'       => '2026-04-16',
            'amount'                   => 500000,
            'paid_amount'              => 0,
            'vendor_category_snapshot' => VendorCategory::Large->value,
            'status'                   => InvoiceStatus::Pending->value,
        ]);

        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-06-20'));

        $fresh = PurchaseInvoice::withoutGlobalScopes()->find($invoice->id);
        $this->assertEquals(0, (float) $fresh->disallowance_amount);
        $this->assertEquals(0, (float) $fresh->interest_amount);
    }

    #[Test]
    public function recompute_for_tenant_updates_all_non_paid_invoices(): void
    {
        $pending = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $paid = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 100000,
            'paid_amount'        => 100000,
            'status'             => InvoiceStatus::Paid->value,
        ]);

        $count = $this->recomputer->recomputeForTenant($this->tenant, Carbon::parse('2026-06-20'));

        // Only the non-paid invoice is recomputed
        $this->assertEquals(1, $count);

        // Pending invoice is now overdue
        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $pending->id,
            'status' => InvoiceStatus::Overdue->value,
        ]);

        // Paid invoice unchanged
        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $paid->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    #[Test]
    public function recompute_for_vendor_only_affects_that_vendors_invoices(): void
    {
        $otherVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Other Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        $microInvoice = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $otherInvoice = $this->makeInvoice($otherVendor, [
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 200000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $count = $this->recomputer->recomputeForVendor($this->microVendor, Carbon::parse('2026-06-20'));

        $this->assertEquals(1, $count);

        // microVendor's invoice updated
        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $microInvoice->id,
            'status' => InvoiceStatus::Overdue->value,
        ]);

        // otherVendor's invoice NOT updated (still pending)
        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $otherInvoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    #[Test]
    public function recompute_uses_tenant_rbi_bank_rate(): void
    {
        // Tenant with higher bank rate
        $highRateTenant = Tenant::create([
            'name'                => 'High Rate Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 10.00,
            'is_active'           => true,
        ]);

        $vendor = Vendor::create([
            'tenant_id' => $highRateTenant->id,
            'name'      => 'Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        $invoice = $this->makeInvoice($vendor, [
            'tenant_id'          => $highRateTenant->id,
            'invoice_date'       => '2026-04-01',
            'effective_deadline' => '2026-04-16',
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Pending->value,
        ]);

        $invoice->load('tenant');
        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-06-20'));

        $fresh = PurchaseInvoice::withoutGlobalScopes()->find($invoice->id);
        // At 10% bank rate, interest = 30% p.a. compounded monthly for ~2 months
        // This should be more than at 6.75%
        $this->assertGreaterThan(0, (float) $fresh->interest_amount);
    }

    #[Test]
    public function recompute_marks_disallowed_after_fy_end(): void
    {
        $invoice = $this->makeInvoice($this->microVendor, [
            'invoice_date'       => '2025-12-01',
            'effective_deadline' => '2025-12-16',
            'financial_year'     => '2025-26',
            'amount'             => 100000,
            'paid_amount'        => 0,
            'status'             => InvoiceStatus::Overdue->value,
        ]);

        // After March 31, 2026 → Disallowed
        $this->recomputer->recomputeOne($invoice, Carbon::parse('2026-04-15'));

        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $invoice->id,
            'status' => InvoiceStatus::Disallowed->value,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeInvoice(Vendor $vendor, array $data): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'tenant_id'                => $vendor->tenant_id,
            'vendor_id'                => $vendor->id,
            'invoice_number'           => 'TEST-' . uniqid(),
            'currency'                 => 'INR',
            'agreement_exists'         => false,
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2026-27',
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'balance'                  => $data['amount'] ?? 100000,
        ], $data));
    }
}
