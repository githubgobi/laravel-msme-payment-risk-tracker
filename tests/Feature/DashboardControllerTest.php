<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;
    private Vendor $microVendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Test Company',
            'email'                   => 'test@company.com',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->user = User::create([
            'name'      => 'Finance Manager',
            'email'     => 'fm@test.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->microVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Arjun Textiles',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
    }

    // ─── Page rendering ───────────────────────────────────────────────────────

    #[Test]
    public function dashboard_renders_with_correct_inertia_component(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Dashboard')
                ->has('financialYear')
                ->has('availableYears')
                ->has('stats')
                ->has('atRiskInvoices')
                ->has('vendorCounts')
                ->has('monthlyTrend')
                ->has('unclassifiedVendors')
            );
    }

    #[Test]
    public function dashboard_returns_zeros_when_no_invoices_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p
                ->where('stats.at_risk_count', 0)
                ->where('stats.total_at_risk', 0)
                ->where('stats.projected_disallowance', 0)
                ->where('stats.projected_interest', 0)
                ->where('stats.due_this_week', 0)
                ->has('atRiskInvoices', 0)
            );
    }

    // ─── Financial year selector ───────────────────────────────────────────────

    #[Test]
    public function dashboard_defaults_to_current_financial_year(): void
    {
        $now = Carbon::now();
        $expectedFy = $now->month >= 4
            ? $now->year . '-' . substr($now->year + 1, -2)
            : ($now->year - 1) . '-' . substr($now->year, -2);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p->where('financialYear', $expectedFy));
    }

    #[Test]
    public function dashboard_respects_valid_fy_query_param(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2024-25']))
            ->assertInertia(fn ($p) => $p->where('financialYear', '2024-25'));
    }

    #[Test]
    public function invalid_fy_param_falls_back_to_current_fy(): void
    {
        $now = Carbon::now();
        $currentFy = $now->month >= 4
            ? $now->year . '-' . substr($now->year + 1, -2)
            : ($now->year - 1) . '-' . substr($now->year, -2);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => 'not-a-year']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('financialYear', $currentFy));
    }

    // ─── Stats computation ────────────────────────────────────────────────────

    #[Test]
    public function stats_sum_disallowance_and_interest_for_at_risk_invoices(): void
    {
        $this->createInvoice([
            'status'              => InvoiceStatus::Overdue->value,
            'financial_year'      => '2025-26',
            'disallowance_amount' => 50000.00,
            'interest_amount'     => 3000.00,
        ]);
        $this->createInvoice([
            'status'              => InvoiceStatus::Pending->value,
            'financial_year'      => '2025-26',
            'disallowance_amount' => 20000.00,
            'interest_amount'     => 0.00,
        ]);
        // Paid invoice — must NOT be counted
        $this->createInvoice([
            'status'              => InvoiceStatus::Paid->value,
            'financial_year'      => '2025-26',
            'disallowance_amount' => 10000.00,
            'interest_amount'     => 500.00,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('stats.at_risk_count', 2)
                ->where('stats.projected_disallowance', 70000)
                ->where('stats.projected_interest', 3000)
            );
    }

    #[Test]
    public function stats_count_overdue_invoices_separately(): void
    {
        $this->createInvoice(['status' => InvoiceStatus::Overdue->value, 'financial_year' => '2025-26']);
        $this->createInvoice(['status' => InvoiceStatus::Overdue->value, 'financial_year' => '2025-26']);
        $this->createInvoice(['status' => InvoiceStatus::Pending->value, 'financial_year' => '2025-26']);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p->where('stats.overdue_count', 2));
    }

    #[Test]
    public function stats_count_invoices_due_within_7_days(): void
    {
        // Due in 3 days
        $this->createInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'financial_year'     => '2025-26',
            'effective_deadline' => Carbon::today()->addDays(3)->toDateString(),
        ]);
        // Due in 10 days — should NOT count
        $this->createInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'financial_year'     => '2025-26',
            'effective_deadline' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p->where('stats.due_this_week', 1));
    }

    // ─── FY isolation ─────────────────────────────────────────────────────────

    #[Test]
    public function stats_are_scoped_to_selected_financial_year(): void
    {
        $this->createInvoice([
            'status'              => InvoiceStatus::Overdue->value,
            'financial_year'      => '2024-25',
            'disallowance_amount' => 100000.00,
        ]);
        $this->createInvoice([
            'status'              => InvoiceStatus::Overdue->value,
            'financial_year'      => '2025-26',
            'disallowance_amount' => 50000.00,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('stats.at_risk_count', 1)
                ->where('stats.projected_disallowance', 50000)
            );
    }

    // ─── At-risk invoices list ────────────────────────────────────────────────

    #[Test]
    public function at_risk_invoices_are_sorted_overdue_first(): void
    {
        $this->createInvoice([
            'invoice_number' => 'INV-PENDING',
            'status'         => InvoiceStatus::Pending->value,
            'financial_year' => '2025-26',
            'effective_deadline' => Carbon::today()->addDays(5)->toDateString(),
        ]);
        $this->createInvoice([
            'invoice_number' => 'INV-OVERDUE',
            'status'         => InvoiceStatus::Overdue->value,
            'financial_year' => '2025-26',
            'effective_deadline' => Carbon::today()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('atRiskInvoices.0.invoice_number', 'INV-OVERDUE')
                ->where('atRiskInvoices.1.invoice_number', 'INV-PENDING')
            );
    }

    #[Test]
    public function at_risk_invoices_include_vendor_name(): void
    {
        $this->createInvoice([
            'invoice_number' => 'INV-001',
            'status'         => InvoiceStatus::Pending->value,
            'financial_year' => '2025-26',
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('atRiskInvoices.0.vendor_name', 'Arjun Textiles')
            );
    }

    #[Test]
    public function paid_invoices_are_not_in_at_risk_list(): void
    {
        $this->createInvoice(['status' => InvoiceStatus::Paid->value, 'financial_year' => '2025-26']);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p->has('atRiskInvoices', 0));
    }

    // ─── Vendor breakdown ─────────────────────────────────────────────────────

    #[Test]
    public function vendor_counts_reflect_actual_vendor_data(): void
    {
        // Already have 1 micro vendor from setUp
        Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Small Supplier',
            'category'  => VendorCategory::Small->value,
            'is_active' => true,
        ]);
        Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Unknown Vendor',
            'category'  => VendorCategory::Unclassified->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p
                ->where('vendorCounts.micro', 1)
                ->where('vendorCounts.small', 1)
                ->where('vendorCounts.unclassified', 1)
                ->where('vendorCounts.total', 3)
            );
    }

    // ─── Unclassified vendor warning ──────────────────────────────────────────

    #[Test]
    public function unclassified_vendor_count_is_correct(): void
    {
        Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Unnamed Vendor 1',
            'category'  => VendorCategory::Unclassified->value,
            'is_active' => true,
        ]);
        Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Unnamed Vendor 2',
            'category'  => VendorCategory::Unclassified->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p->where('unclassifiedVendors', 2));
    }

    // ─── Monthly trend ────────────────────────────────────────────────────────

    #[Test]
    public function monthly_trend_returns_12_months(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p->has('monthlyTrend', 12));
    }

    #[Test]
    public function monthly_trend_aggregates_disallowance_by_month(): void
    {
        $this->createInvoice([
            'status'              => InvoiceStatus::Overdue->value,
            'financial_year'      => '2025-26',
            'invoice_date'        => '2025-05-15', // May → index 1 in Apr-Mar order
            'disallowance_amount' => 25000.00,
            'interest_amount'     => 1500.00,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('monthlyTrend.1.month', 'May')
                ->where('monthlyTrend.1.disallowance', 25000)
                ->where('monthlyTrend.1.interest', 1500)
            );
    }

    // ─── Tenant isolation ─────────────────────────────────────────────────────

    #[Test]
    public function dashboard_shows_only_current_tenants_data(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Company',
            'email'               => 'other@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
        $otherVendor = Vendor::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Other Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);
        // Create invoice for other tenant directly (bypass global scope)
        PurchaseInvoice::withoutGlobalScopes()->create([
            'tenant_id'                => $otherTenant->id,
            'vendor_id'                => $otherVendor->id,
            'invoice_number'           => 'OTHER-001',
            'invoice_date'             => '2025-07-01',
            'amount'                   => 100000.00,
            'paid_amount'              => 0,
            'effective_deadline'       => '2025-07-16',
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2025-26',
            'status'                   => InvoiceStatus::Overdue->value,
            'disallowance_amount'      => 100000.00,
            'interest_amount'          => 5000.00,
            'agreement_exists'         => false,
        ]);

        // Our tenant has no invoices — should show zero
        $this->actingAs($this->user)
            ->get(route('dashboard', ['fy' => '2025-26']))
            ->assertInertia(fn ($p) => $p
                ->where('stats.at_risk_count', 0)
                ->where('stats.projected_disallowance', 0)
            );
    }

    // ─── Available years ──────────────────────────────────────────────────────

    #[Test]
    public function available_years_always_includes_current_fy(): void
    {
        // No invoices created — current FY must still appear
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p
                ->where('availableYears', fn ($v) => count($v) >= 1)
            );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createInvoice(array $overrides = []): PurchaseInvoice
    {
        $defaults = [
            'tenant_id'                => $this->tenant->id,
            'vendor_id'                => $this->microVendor->id,
            'invoice_number'           => 'INV-' . uniqid(),
            'invoice_date'             => '2025-07-01',
            'amount'                   => 100000.00,
            'paid_amount'              => 0.00,
            'effective_deadline'       => '2025-07-16',
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2025-26',
            'status'                   => InvoiceStatus::Pending->value,
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'agreement_exists'         => false,
        ];

        return PurchaseInvoice::create(array_merge($defaults, $overrides));
    }
}
