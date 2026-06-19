<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant        $tenant;
    private User          $owner;
    private Vendor        $vendor;
    private PurchaseInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                => 'Payment Test Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'subscription_ends_at' => now()->addYear(),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        // Invoice with outstanding balance of ₹100,000
        $this->invoice = PurchaseInvoice::create([
            'tenant_id'                => $this->tenant->id,
            'vendor_id'                => $this->vendor->id,
            'invoice_number'           => 'PAY-TEST-001',
            'invoice_date'             => '2026-06-01',
            'amount'                   => 100000,
            'paid_amount'              => 0.00,
            'balance'                  => 100000,
            'currency'                 => 'INR',
            'agreement_exists'         => false,
            'effective_deadline'       => '2026-07-15',
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2026-27',
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'status'                   => InvoiceStatus::Pending->value,
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_record_payment(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 50000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'neft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'amount'     => 50000,
        ]);
    }

    #[Test]
    public function payment_updates_invoice_paid_amount(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 60000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'rtgs',
            ]);

        $this->assertDatabaseHas('purchase_invoices', [
            'id'          => $this->invoice->id,
            'paid_amount' => 60000,
        ]);
    }

    #[Test]
    public function full_payment_triggers_risk_recompute_to_paid_status(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 100000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'neft',
            ]);

        $this->assertDatabaseHas('purchase_invoices', [
            'id'     => $this->invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    #[Test]
    public function payment_exceeding_balance_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 150000, // More than 100000 balance
                'payment_date' => '2026-06-15',
                'payment_mode' => 'neft',
            ])
            ->assertSessionHasErrors('amount');
    }

    #[Test]
    public function future_payment_date_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 50000,
                'payment_date' => '2099-01-01',
                'payment_mode' => 'neft',
            ])
            ->assertSessionHasErrors('payment_date');
    }

    #[Test]
    public function invalid_payment_mode_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 50000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'bitcoin',
            ])
            ->assertSessionHasErrors('payment_mode');
    }

    #[Test]
    public function finance_user_can_record_payment(): void
    {
        $finance = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 50000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'upi',
            ])
            ->assertRedirect();
    }

    #[Test]
    public function viewer_cannot_record_payment(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Viewer->value,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->post("/invoices/{$this->invoice->id}/payments", [
                'amount'       => 50000,
                'payment_date' => '2026-06-15',
                'payment_mode' => 'neft',
            ])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_delete_payment_and_it_recomputes_risk(): void
    {
        // First record a payment
        $payment = Payment::create([
            'tenant_id'    => $this->tenant->id,
            'invoice_id'   => $this->invoice->id,
            'payment_date' => '2026-06-15',
            'amount'       => 100000,
            'payment_mode' => 'neft',
        ]);

        // Update paid_amount to reflect this payment
        $this->invoice->update(['paid_amount' => 100000, 'status' => InvoiceStatus::Paid->value]);

        $this->actingAs($this->owner)
            ->delete("/invoices/{$this->invoice->id}/payments/{$payment->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);

        // Invoice should revert to pending (balance restored, deadline in future)
        $this->assertDatabaseHas('purchase_invoices', [
            'id'          => $this->invoice->id,
            'paid_amount' => 0,
        ]);
    }

    #[Test]
    public function cannot_delete_payment_from_different_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'subscription_ends_at' => now()->addYear(),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
        $otherVendor = Vendor::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other',
            'category'  => VendorCategory::Micro->value, 'is_active' => true,
        ]);
        $otherInvoice = PurchaseInvoice::create([
            'tenant_id'                => $otherTenant->id,
            'vendor_id'                => $otherVendor->id,
            'invoice_number'           => 'OTHER-001',
            'invoice_date'             => '2026-06-01',
            'amount'                   => 50000,
            'paid_amount'              => 0,
            'balance'                  => 50000,
            'currency'                 => 'INR',
            'agreement_exists'         => false,
            'effective_deadline'       => '2026-07-01',
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2026-27',
            'disallowance_amount'      => 0,
            'interest_amount'          => 0,
            'status'                   => InvoiceStatus::Pending->value,
        ]);
        $otherPayment = Payment::create([
            'tenant_id'    => $otherTenant->id,
            'invoice_id'   => $otherInvoice->id,
            'payment_date' => '2026-06-10',
            'amount'       => 50000,
            'payment_mode' => 'neft',
        ]);

        // TenantScope prevents the other tenant's payment from resolving — 404
        $this->actingAs($this->owner)
            ->delete("/invoices/{$this->invoice->id}/payments/{$otherPayment->id}")
            ->assertNotFound();
    }
}
