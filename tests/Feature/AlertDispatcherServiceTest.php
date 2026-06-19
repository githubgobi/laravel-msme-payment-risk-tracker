<?php

namespace Tests\Feature;

use App\Enums\AlertChannel;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Jobs\SendAlertJob;
use App\Models\AlertLog;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AlertDispatcherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertDispatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;
    private Vendor $vendor;
    private AlertDispatcherService $dispatcher;
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->today = Carbon::parse('2025-09-01')->startOfDay();
        Carbon::setTestNow($this->today);

        $this->tenant = Tenant::create([
            'name'                => 'Alpha Corp',
            'email'               => 'alpha@corp.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
            'settings'            => [
                'alerts' => [
                    'email_enabled'    => true,
                    'email_recipients' => ['finance@alpha.com'],
                    't10_enabled'      => true,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        $this->user = User::create([
            'name'      => 'Finance Manager',
            'email'     => 'finance@alpha.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Rajan Textiles',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        $this->dispatcher = app(AlertDispatcherService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time
        parent::tearDown();
    }

    // ─── T10Warning window ─────────────────────────────────────────────────────

    #[Test]
    public function invoice_with_deadline_in_t10_window_qualifies(): void
    {
        $invoice = $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9), // within [+8,+10]
        ]);

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T10Warning, $this->today);

        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id, $invoices->first()->id);
    }

    #[Test]
    public function invoice_with_deadline_outside_t10_window_does_not_qualify(): void
    {
        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(11), // outside [+8,+10]
        ]);

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T10Warning, $this->today);

        $this->assertEmpty($invoices);
    }

    // ─── T3Urgent window ──────────────────────────────────────────────────────

    #[Test]
    public function invoice_with_deadline_in_t3_window_qualifies(): void
    {
        $invoice = $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(2), // within [+1,+3]
        ]);

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T3Urgent, $this->today);

        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id, $invoices->first()->id);
    }

    #[Test]
    public function invoice_with_deadline_outside_t3_window_does_not_qualify(): void
    {
        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(4), // outside [+1,+3]
        ]);

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T3Urgent, $this->today);

        $this->assertEmpty($invoices);
    }

    // ─── Overdue ──────────────────────────────────────────────────────────────

    #[Test]
    public function overdue_invoice_qualifies_for_overdue_alert(): void
    {
        $invoice = $this->makeInvoice([
            'status'             => InvoiceStatus::Overdue->value,
            'effective_deadline' => $this->today->copy()->subDays(5),
        ]);

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::Overdue, $this->today);

        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id, $invoices->first()->id);
    }

    #[Test]
    public function paid_invoice_does_not_qualify_for_any_alert_type(): void
    {
        $this->makeInvoice([
            'status'             => InvoiceStatus::Paid->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $t10 = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T10Warning, $this->today);
        $t3  = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T3Urgent, $this->today);
        $od  = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::Overdue, $this->today);

        $this->assertEmpty($t10);
        $this->assertEmpty($t3);
        $this->assertEmpty($od);
    }

    // ─── Year-end summary ─────────────────────────────────────────────────────

    #[Test]
    public function year_end_summary_returns_empty_collection_always(): void
    {
        $this->makeInvoice();

        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::YearEndSummary, $this->today);

        $this->assertEmpty($invoices);
    }

    // ─── dispatchForTenant ────────────────────────────────────────────────────

    #[Test]
    public function dispatch_creates_alert_log_and_queues_job_for_qualifying_invoice(): void
    {
        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $result = $this->dispatcher->dispatchForTenant($this->tenant, $this->today);

        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('alert_log', [
            'tenant_id'  => $this->tenant->id,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'finance@alpha.com',
            'alert_type' => AlertType::T10Warning->value,
            'status'     => AlertStatus::Pending->value,
        ]);

        Bus::assertDispatched(SendAlertJob::class);
    }

    // ─── Deduplication ────────────────────────────────────────────────────────

    #[Test]
    public function duplicate_alert_for_same_invoice_today_is_skipped(): void
    {
        $invoice = $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        // Simulate an already-sent alert today
        AlertLog::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'finance@alpha.com',
            'alert_type' => AlertType::T10Warning->value,
            'status'     => AlertStatus::Sent->value,
        ]);

        $result = $this->dispatcher->dispatchForTenant($this->tenant, $this->today);

        $this->assertEquals(0, $result['dispatched']);
        $this->assertEquals(1, $result['skipped']);
        Bus::assertNotDispatched(SendAlertJob::class);
    }

    // ─── Channel resolution ───────────────────────────────────────────────────

    #[Test]
    public function email_disabled_in_settings_produces_no_channels(): void
    {
        $this->tenant->update([
            'settings' => [
                'alerts' => [
                    'email_enabled'    => false,
                    'whatsapp_enabled' => false,
                ],
            ],
        ]);

        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $result = $this->dispatcher->dispatchForTenant($this->tenant, $this->today);

        $this->assertEquals(0, $result['dispatched']);
        Bus::assertNotDispatched(SendAlertJob::class);
    }

    #[Test]
    public function whatsapp_channel_included_when_configured(): void
    {
        $this->tenant->update([
            'settings' => [
                'alerts' => [
                    'email_enabled'    => true,
                    'email_recipients' => ['finance@alpha.com'],
                    'whatsapp_enabled' => true,
                    'whatsapp_number'  => '+919876543210',
                    't10_enabled'      => true,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $result = $this->dispatcher->dispatchForTenant($this->tenant, $this->today);

        // 1 email + 1 WhatsApp = 2 dispatched for the same invoice
        $this->assertEquals(2, $result['dispatched']);
        Bus::assertDispatched(SendAlertJob::class, 2);
    }

    #[Test]
    public function t10_disabled_in_settings_skips_t10_alerts(): void
    {
        $this->tenant->update([
            'settings' => [
                'alerts' => [
                    'email_enabled'    => true,
                    'email_recipients' => ['finance@alpha.com'],
                    't10_enabled'      => false,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $result = $this->dispatcher->dispatchForTenant($this->tenant, $this->today);

        $this->assertEquals(0, $result['dispatched']);
        Bus::assertNotDispatched(SendAlertJob::class);
    }

    #[Test]
    public function fallback_to_all_users_emails_when_no_recipients_configured(): void
    {
        $this->tenant->update([
            'settings' => [
                'alerts' => [
                    'email_enabled'    => true,
                    'email_recipients' => [], // Empty — should fallback to user emails
                    't10_enabled'      => true,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        $this->makeInvoice([
            'status'             => InvoiceStatus::Pending->value,
            'effective_deadline' => $this->today->copy()->addDays(9),
        ]);

        $channels = $this->dispatcher->resolveChannels($this->tenant, $this->tenant->settings['alerts']);

        $this->assertCount(1, $channels);
        $this->assertEquals('finance@alpha.com', $channels[0][1]); // fallback to user email
    }

    // ─── Cross-tenant isolation ───────────────────────────────────────────────

    #[Test]
    public function invoices_from_other_tenants_do_not_qualify(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Beta Corp',
            'subscription_status' => TenantStatus::Active->value,
            'is_active'           => true,
            'plan'                => TenantPlan::Starter->value,
            'rbi_bank_rate'       => 6.75,
        ]);

        $otherVendor = Vendor::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Other Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);

        // Create invoice for other tenant
        PurchaseInvoice::create([
            'tenant_id'               => $otherTenant->id,
            'vendor_id'               => $otherVendor->id,
            'invoice_number'          => 'INV-OTHER-001',
            'invoice_date'            => $this->today->copy()->subDays(20),
            'amount'                  => 50000,
            'paid_amount'             => 0,
            'effective_deadline'      => $this->today->copy()->addDays(9),
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'          => '2025-26',
            'status'                  => InvoiceStatus::Pending->value,
            'agreement_exists'        => false,
            'disallowance_amount'     => 0,
            'interest_amount'         => 0,
        ]);

        // Dispatch for THIS tenant — should find 0 qualifying invoices
        $invoices = $this->dispatcher->qualifyingInvoices($this->tenant, AlertType::T10Warning, $this->today);

        $this->assertEmpty($invoices);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeInvoice(array $attrs = []): PurchaseInvoice
    {
        static $counter = 0;
        $counter++;

        return PurchaseInvoice::create(array_merge([
            'tenant_id'               => $this->tenant->id,
            'vendor_id'               => $this->vendor->id,
            'invoice_number'          => "INV-{$counter}",
            'invoice_date'            => $this->today->copy()->subDays(20),
            'amount'                  => 50000,
            'paid_amount'             => 0,
            'effective_deadline'      => $this->today->copy()->addDays(15),
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'          => '2025-26',
            'status'                  => InvoiceStatus::Pending->value,
            'agreement_exists'        => false,
            'disallowance_amount'     => 0,
            'interest_amount'         => 0,
        ], $attrs));
    }
}
