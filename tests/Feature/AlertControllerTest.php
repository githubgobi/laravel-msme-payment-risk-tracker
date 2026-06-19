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
use App\Models\AlertLog;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                => 'Alpha Corp',
            'email'               => 'alpha@corp.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->user = User::create([
            'name'      => 'Finance Manager',
            'email'     => 'fm@alpha.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->get(route('alerts.index'))->assertRedirect('/login');
    }

    #[Test]
    public function settings_update_requires_authentication(): void
    {
        $this->putJson(route('alerts.settings'), [])->assertRedirect('/login');
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Test]
    public function index_renders_alerts_inertia_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->has('logs')
                  ->has('summary')
                  ->has('settings')
                  ->has('channels')
                  ->has('alertTypes')
                  ->has('statuses')
            );
    }

    #[Test]
    public function index_includes_summary_stats(): void
    {
        $this->actingAs($this->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->where('summary.total_this_month', 0)
                  ->where('summary.sent',             0)
                  ->where('summary.failed',           0)
                  ->where('summary.pending',          0)
            );
    }

    #[Test]
    public function index_includes_default_settings_when_none_configured(): void
    {
        $this->actingAs($this->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->where('settings.email_enabled',    true)
                  ->where('settings.whatsapp_enabled', false)
                  ->where('settings.t10_enabled',      true)
                  ->where('settings.t3_enabled',       true)
                  ->where('settings.overdue_enabled',  true)
            );
    }

    #[Test]
    public function index_shows_alert_logs_for_current_tenant(): void
    {
        $vendor  = $this->makeVendor();
        $invoice = $this->makeInvoice($vendor);

        AlertLog::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'fm@alpha.com',
            'alert_type' => AlertType::T10Warning->value,
            'status'     => AlertStatus::Sent->value,
        ]);

        $this->actingAs($this->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->where('logs.total', 1)
                  ->where('logs.data.0.channel', 'email')
                  ->where('logs.data.0.alert_type', 't10_warning')
                  ->where('logs.data.0.status', 'sent')
            );
    }

    #[Test]
    public function index_hides_alert_logs_from_other_tenants(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Beta Corp',
            'subscription_status' => TenantStatus::Active->value,
            'is_active'           => true,
            'plan'                => TenantPlan::Starter->value,
            'rbi_bank_rate'       => 6.75,
        ]);

        // AlertLog for a different tenant (no invoice)
        AlertLog::withoutGlobalScopes()->create([
            'tenant_id'  => $otherTenant->id,
            'invoice_id' => null,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'other@beta.com',
            'alert_type' => AlertType::Overdue->value,
            'status'     => AlertStatus::Sent->value,
        ]);

        $this->actingAs($this->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->where('logs.total', 0)
            );
    }

    #[Test]
    public function index_filters_by_status(): void
    {
        $vendor  = $this->makeVendor();
        $invoice = $this->makeInvoice($vendor);

        AlertLog::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'fm@alpha.com',
            'alert_type' => AlertType::T10Warning->value,
            'status'     => AlertStatus::Sent->value,
        ]);

        AlertLog::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'channel'    => AlertChannel::Email->value,
            'recipient'  => 'fm@alpha.com',
            'alert_type' => AlertType::T3Urgent->value,
            'status'     => AlertStatus::Failed->value,
        ]);

        $this->actingAs($this->user)
            ->get(route('alerts.index') . '?status=failed')
            ->assertOk()
            ->assertInertia(fn ($p) =>
                $p->component('Alerts/Index')
                  ->where('logs.total', 1)
                  ->where('logs.data.0.status', 'failed')
            );
    }

    // ─── Settings update ──────────────────────────────────────────────────────

    #[Test]
    public function settings_can_be_updated(): void
    {
        $this->actingAs($this->user)
            ->put(route('alerts.settings'), [
                'email_enabled'    => true,
                'whatsapp_enabled' => true,
                'email_recipients' => ['gobi@alpha.com'],
                'whatsapp_number'  => '+919876543210',
                't10_enabled'      => true,
                't3_enabled'       => false,
                'overdue_enabled'  => true,
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $settings = $this->tenant->settings['alerts'];

        $this->assertTrue($settings['email_enabled']);
        $this->assertTrue($settings['whatsapp_enabled']);
        $this->assertEquals(['gobi@alpha.com'], $settings['email_recipients']);
        $this->assertEquals('+919876543210',    $settings['whatsapp_number']);
        $this->assertFalse($settings['t3_enabled']);
    }

    #[Test]
    public function settings_preserves_existing_tenant_settings_keys(): void
    {
        $this->tenant->update([
            'settings' => ['some_other_key' => 'preserved_value'],
        ]);

        $this->actingAs($this->user)
            ->put(route('alerts.settings'), [
                'email_enabled' => true,
            ])
            ->assertRedirect();

        $this->tenant->refresh();

        $this->assertEquals('preserved_value', $this->tenant->settings['some_other_key']);
    }

    #[Test]
    public function settings_update_rejects_invalid_email_recipient(): void
    {
        $this->actingAs($this->user)
            ->put(route('alerts.settings'), [
                'email_enabled'    => true,
                'email_recipients' => ['not-an-email'],
            ])
            ->assertSessionHasErrors(['email_recipients.0']);
    }

    #[Test]
    public function settings_update_rejects_invalid_whatsapp_number(): void
    {
        $this->actingAs($this->user)
            ->put(route('alerts.settings'), [
                'whatsapp_enabled' => true,
                'whatsapp_number'  => 'not-a-number',
            ])
            ->assertSessionHasErrors(['whatsapp_number']);
    }

    #[Test]
    public function settings_update_accepts_empty_recipient_array(): void
    {
        $this->actingAs($this->user)
            ->put(route('alerts.settings'), [
                'email_enabled'    => true,
                'email_recipients' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeVendor(): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => VendorCategory::Micro->value,
            'is_active' => true,
        ]);
    }

    private function makeInvoice(Vendor $vendor): PurchaseInvoice
    {
        return PurchaseInvoice::create([
            'tenant_id'               => $this->tenant->id,
            'vendor_id'               => $vendor->id,
            'invoice_number'          => 'INV-CTRL-001',
            'invoice_date'            => Carbon::today()->subDays(20),
            'amount'                  => 50000,
            'paid_amount'             => 0,
            'effective_deadline'      => Carbon::today()->addDays(9),
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'          => '2025-26',
            'status'                  => InvoiceStatus::Pending->value,
            'agreement_exists'        => false,
            'disallowance_amount'     => 0,
            'interest_amount'         => 0,
        ]);
    }
}
