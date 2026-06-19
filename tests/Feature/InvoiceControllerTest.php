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
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Invoice Test Corp',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'subscription_ends_at'    => now()->addYear(),
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
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
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Test]
    public function guest_is_redirected_from_invoices(): void
    {
        $this->get('/invoices')->assertRedirect('/login');
    }

    #[Test]
    public function index_renders_with_required_props(): void
    {
        $this->makeInvoice();

        $this->actingAs($this->owner)
            ->get('/invoices')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Invoices/Index')
                ->has('invoices')
                ->has('filters')
                ->has('vendors')
                ->has('financial_years')
                ->has('statuses')
                ->has('summary')
            );
    }

    #[Test]
    public function index_returns_tenant_invoices_only(): void
    {
        $this->makeInvoice(['invoice_number' => 'MY-INV-001']);

        $otherTenant = Tenant::create([
            'name'                => 'Other Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'subscription_ends_at' => now()->addYear(),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
        $otherVendor = Vendor::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Other Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);
        $this->makeInvoice(['invoice_number' => 'OTHER-INV-001', 'tenant_id' => $otherTenant->id, 'vendor_id' => $otherVendor->id]);

        $this->actingAs($this->owner)
            ->get('/invoices')
            ->assertInertia(fn (Assert $p) => $p
                ->where('invoices.total', 1)
            );
    }

    #[Test]
    public function status_filter_returns_only_matching_invoices(): void
    {
        $this->makeInvoice(['invoice_number' => 'OVERDUE-001', 'status' => InvoiceStatus::Overdue->value]);
        $this->makeInvoice(['invoice_number' => 'PENDING-001', 'status' => InvoiceStatus::Pending->value]);

        $this->actingAs($this->owner)
            ->get('/invoices?status=overdue')
            ->assertInertia(fn (Assert $p) => $p
                ->where('invoices.total', 1)
            );
    }

    #[Test]
    public function financial_year_filter_returns_correct_invoices(): void
    {
        $this->makeInvoice(['invoice_number' => 'FY26-001', 'financial_year' => '2026-27']);
        $this->makeInvoice(['invoice_number' => 'FY25-001', 'financial_year' => '2025-26']);

        $this->actingAs($this->owner)
            ->get('/invoices?financial_year=2025-26')
            ->assertInertia(fn (Assert $p) => $p
                ->where('invoices.total', 1)
            );
    }

    #[Test]
    public function search_by_invoice_number_works(): void
    {
        $this->makeInvoice(['invoice_number' => 'INV-ALPHA-001']);
        $this->makeInvoice(['invoice_number' => 'INV-BETA-001']);

        $this->actingAs($this->owner)
            ->get('/invoices?search=ALPHA')
            ->assertInertia(fn (Assert $p) => $p
                ->where('invoices.total', 1)
            );
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    #[Test]
    public function show_renders_invoice_detail(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->owner)
            ->get("/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Invoices/Show')
                ->has('invoice')
                ->has('paymentModes')
                ->has('canManage')
            );
    }

    #[Test]
    public function show_returns_404_for_other_tenants_invoice(): void
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
            'tenant_id' => $otherTenant->id, 'name' => 'Other Vendor',
            'category'  => VendorCategory::Micro->value, 'is_active' => true,
        ]);
        $otherInvoice = $this->makeInvoice([
            'tenant_id' => $otherTenant->id,
            'vendor_id' => $otherVendor->id,
        ]);

        $this->actingAs($this->owner)
            ->get("/invoices/{$otherInvoice->id}")
            ->assertStatus(404);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_update_invoice_narration(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->owner)
            ->put("/invoices/{$invoice->id}", ['narration' => 'Monthly supply batch'])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_invoices', [
            'id'        => $invoice->id,
            'narration' => 'Monthly supply batch',
        ]);
    }

    #[Test]
    public function toggling_agreement_updates_effective_deadline(): void
    {
        $invoice = $this->makeInvoice([
            'invoice_date'       => '2026-06-01',
            'agreement_exists'   => false,
            'effective_deadline' => '2026-06-16', // 15 days
        ]);

        $this->actingAs($this->owner)
            ->put("/invoices/{$invoice->id}", ['agreement_exists' => true])
            ->assertRedirect();

        // Deadline should now be invoice_date + 45 days = 2026-07-16
        $this->assertDatabaseHas('purchase_invoices', [
            'id'                 => $invoice->id,
            'agreement_exists'   => 1,
            'effective_deadline' => '2026-07-16',
        ]);
    }

    #[Test]
    public function viewer_cannot_update_invoice(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Viewer->value,
            'is_active' => true,
        ]);
        $invoice = $this->makeInvoice();

        $this->actingAs($viewer)
            ->put("/invoices/{$invoice->id}", ['narration' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_soft_delete_invoice_without_payments(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->owner)
            ->delete("/invoices/{$invoice->id}")
            ->assertRedirect('/invoices');

        $this->assertSoftDeleted('purchase_invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function cannot_delete_invoice_with_payments(): void
    {
        $invoice = $this->makeInvoice();

        Payment::create([
            'tenant_id'    => $this->tenant->id,
            'invoice_id'   => $invoice->id,
            'payment_date' => '2026-06-01',
            'amount'       => 10000,
            'payment_mode' => 'neft',
        ]);

        $this->actingAs($this->owner)
            ->delete("/invoices/{$invoice->id}")
            ->assertSessionHasErrors();
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeInvoice(array $overrides = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'tenant_id'                => $this->tenant->id,
            'vendor_id'                => $this->vendor->id,
            'invoice_number'           => 'INV-TEST-' . uniqid(),
            'invoice_date'             => '2026-06-01',
            'amount'                   => 100000,
            'paid_amount'              => 0.00,
            'balance'                  => 100000,
            'currency'                 => 'INR',
            'agreement_exists'         => false,
            'effective_deadline'       => '2026-06-16',
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2026-27',
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'status'                   => InvoiceStatus::Pending->value,
        ], $overrides));
    }
}
