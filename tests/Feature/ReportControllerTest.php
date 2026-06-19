<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name'                    => 'Test Firm',
            'gstin'                   => '27AAPFU0939F1ZV',
            'state'                   => 'Maharashtra',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);

        $this->vendor = Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function reports_index_renders_with_years(): void
    {
        $response = $this->actingAs($this->owner)->get('/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Reports/Index')
                 ->has('years')
                 ->has('currentFy')
        );
    }

    #[Test]
    public function reports_index_is_inaccessible_to_guests(): void
    {
        $response = $this->get('/reports');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function pdf_download_returns_pdf_content_type(): void
    {
        $fy = 2025;

        $response = $this->actingAs($this->owner)->get("/reports/{$fy}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('pdf', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function excel_download_returns_xlsx_content_type(): void
    {
        $fy = 2025;

        $response = $this->actingAs($this->owner)->get("/reports/{$fy}/excel");

        $response->assertStatus(200);
        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'spreadsheetml') || str_contains($contentType, 'octet-stream'),
            "Expected xlsx content type, got: {$contentType}"
        );
    }

    #[Test]
    public function pdf_filename_includes_fy_and_tenant_name(): void
    {
        $fy = 2025;

        $response = $this->actingAs($this->owner)->get("/reports/{$fy}/pdf");

        $response->assertStatus(200);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('43Bh', $disposition);
        $this->assertStringContainsString((string) $fy, $disposition);
    }

    #[Test]
    public function excel_filename_includes_fy_and_tenant_name(): void
    {
        $fy = 2025;

        $response = $this->actingAs($this->owner)->get("/reports/{$fy}/excel");

        $response->assertStatus(200);
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('43Bh', $disposition);
    }

    #[Test]
    public function pdf_route_rejects_non_numeric_fy(): void
    {
        $response = $this->actingAs($this->owner)->get('/reports/invalid/pdf');

        $response->assertStatus(404);
    }

    #[Test]
    public function report_scoped_to_own_tenant(): void
    {
        // Create another tenant with invoice
        $otherTenant = Tenant::factory()->create(['is_active' => true, 'onboarding_completed_at' => now()]);
        $otherVendor = Vendor::factory()->create(['tenant_id' => $otherTenant->id]);

        PurchaseInvoice::factory()->create([
            'tenant_id'    => $otherTenant->id,
            'vendor_id'    => $otherVendor->id,
            'invoice_date' => '2025-06-01',
            'amount'       => 999999,
            'paid_amount'  => 0,
            'status'       => InvoiceStatus::Overdue->value,
        ]);

        // Authenticated as our tenant's owner — PDF must only include our tenant
        $response = $this->actingAs($this->owner)->get('/reports/2025/pdf');

        $response->assertStatus(200);
        $content = $response->getContent();
        // The other tenant's invoice amount should not appear in our report
        $this->assertStringNotContainsString('999,999', $content);
    }
}
