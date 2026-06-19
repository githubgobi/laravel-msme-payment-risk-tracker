<?php

namespace Tests\Unit\Services;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Jobs\PropagateVendorClassification;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\VendorClassificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VendorClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant                     $tenant;
    private VendorClassificationService $service;

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

        $this->service = app(VendorClassificationService::class);

        Bus::fake();
    }

    #[Test]
    public function classify_updates_vendor_category(): void
    {
        $vendor = $this->makeVendor(VendorCategory::Unclassified);

        $this->service->classify($vendor, VendorCategory::Micro);

        $this->assertDatabaseHas('vendors', [
            'id'       => $vendor->id,
            'category' => VendorCategory::Micro->value,
        ]);
    }

    #[Test]
    public function classify_dispatches_propagation_job_when_category_changes(): void
    {
        $vendor = $this->makeVendor(VendorCategory::Unclassified);

        $this->service->classify($vendor, VendorCategory::Micro);

        Bus::assertDispatched(PropagateVendorClassification::class);
    }

    #[Test]
    public function classify_does_not_dispatch_job_when_category_unchanged(): void
    {
        $vendor = $this->makeVendor(VendorCategory::Micro);

        $this->service->classify($vendor, VendorCategory::Micro);

        Bus::assertNotDispatched(PropagateVendorClassification::class);
    }

    #[Test]
    public function classify_records_verification_source(): void
    {
        $vendor = $this->makeVendor(VendorCategory::Unclassified);

        $this->service->classify(
            vendor:      $vendor,
            category:    VendorCategory::Small,
            source:      VendorVerificationSource::Api,
            verifiedAt:  Carbon::parse('2026-06-01'),
        );

        $this->assertDatabaseHas('vendors', [
            'id'                  => $vendor->id,
            'category'            => VendorCategory::Small->value,
            'verification_source' => VendorVerificationSource::Api->value,
        ]);
    }

    #[Test]
    public function classify_updates_udyam_number_when_provided(): void
    {
        $vendor = $this->makeVendor(VendorCategory::Unclassified);

        $this->service->classify(
            vendor:      $vendor,
            category:    VendorCategory::Micro,
            udyamNumber: 'UDYAM-KA-01-0001234',
        );

        $this->assertDatabaseHas('vendors', [
            'id'           => $vendor->id,
            'udyam_number' => 'UDYAM-KA-01-0001234',
        ]);
    }

    #[Test]
    public function bulk_classify_returns_count_of_changed_vendors(): void
    {
        $v1 = $this->makeVendor(VendorCategory::Unclassified);
        $v2 = $this->makeVendor(VendorCategory::Unclassified);
        $v3 = $this->makeVendor(VendorCategory::Micro); // already Micro — won't count

        $changed = $this->service->bulkClassify([$v1, $v2, $v3], VendorCategory::Micro);

        $this->assertEquals(2, $changed);
    }

    #[Test]
    public function bulk_classify_skips_vendors_already_in_target_category(): void
    {
        $v1 = $this->makeVendor(VendorCategory::Micro);
        $v2 = $this->makeVendor(VendorCategory::Micro);

        $this->service->bulkClassify([$v1, $v2], VendorCategory::Micro);

        Bus::assertNotDispatched(PropagateVendorClassification::class);
    }

    #[Test]
    public function bulk_classify_dispatches_job_for_each_changed_vendor(): void
    {
        $v1 = $this->makeVendor(VendorCategory::Unclassified);
        $v2 = $this->makeVendor(VendorCategory::Unclassified);

        $this->service->bulkClassify([$v1, $v2], VendorCategory::Micro);

        Bus::assertDispatchedTimes(PropagateVendorClassification::class, 2);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeVendor(VendorCategory $category): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => fake()->company(),
            'category'  => $category->value,
            'is_active' => true,
        ]);
    }
}
