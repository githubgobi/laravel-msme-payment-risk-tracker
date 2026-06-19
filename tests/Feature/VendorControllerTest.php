<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Jobs\PropagateVendorClassification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VendorControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->tenant = Tenant::create([
            'name'                => 'Test Company',
            'email'               => 'test@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->user = User::create([
            'name'      => 'Test User',
            'email'     => 'user@test.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->get(route('vendors.index'))->assertRedirect('/login');
    }

    #[Test]
    public function index_renders_vendors_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('vendors.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Vendors/Index')
                ->has('vendors')
                ->has('summary')
                ->has('filters')
                ->has('categories')
            );
    }

    #[Test]
    public function index_shows_only_tenant_vendors(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Company',
            'email'               => 'other@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'My Vendor', 'category' => 'unclassified', 'is_active' => true]);
        Vendor::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Vendor', 'category' => 'micro', 'is_active' => true]);

        $this->actingAs($this->user)
            ->get(route('vendors.index'))
            ->assertInertia(fn ($p) =>
                $p->has('vendors.data', 1)->where('vendors.data.0.name', 'My Vendor')
            );
    }

    #[Test]
    public function index_filters_by_category(): void
    {
        Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Micro Vendor', 'category' => 'micro', 'is_active' => true]);
        Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Large Vendor', 'category' => 'large', 'is_active' => true]);

        $this->actingAs($this->user)
            ->get(route('vendors.index', ['category' => 'micro']))
            ->assertInertia(fn ($p) =>
                $p->has('vendors.data', 1)->where('vendors.data.0.name', 'Micro Vendor')
            );
    }

    #[Test]
    public function index_searches_by_name(): void
    {
        Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Arjun Textiles', 'category' => 'micro', 'is_active' => true]);
        Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Rajan Engineering', 'category' => 'small', 'is_active' => true]);

        $this->actingAs($this->user)
            ->get(route('vendors.index', ['search' => 'arjun']))
            ->assertInertia(fn ($p) =>
                $p->has('vendors.data', 1)->where('vendors.data.0.name', 'Arjun Textiles')
            );
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    #[Test]
    public function show_renders_vendor_detail_page(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('vendors.show', $vendor))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Vendors/Show')
                ->where('vendor.id', $vendor->id)
                ->where('vendor.name', 'Test Vendor')
                ->has('stats')
                ->has('invoices')
                ->has('categories')
            );
    }

    #[Test]
    public function show_returns_404_for_other_tenants_vendor(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Co',
            'email'               => 'other2@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $vendor = Vendor::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Foreign Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->get(route('vendors.show', $vendor))
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function update_changes_vendor_name_and_category(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Old Name',
            'category'  => 'unclassified',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'     => 'New Name',
                'category' => 'micro',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendors', [
            'id'       => $vendor->id,
            'name'     => 'New Name',
            'category' => 'micro',
        ]);
    }

    #[Test]
    public function update_dispatches_propagation_job_when_category_changes(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'unclassified',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'     => 'Test Vendor',
                'category' => 'small',
            ]);

        Bus::assertDispatched(PropagateVendorClassification::class);
    }

    #[Test]
    public function update_does_not_dispatch_job_when_category_unchanged(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'     => 'Test Vendor Updated',
                'category' => 'micro', // same
            ]);

        Bus::assertNotDispatched(PropagateVendorClassification::class);
    }

    #[Test]
    public function update_rejects_invalid_gstin(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'     => 'Test Vendor',
                'category' => 'micro',
                'gstin'    => 'INVALID-GSTIN',
            ])
            ->assertSessionHasErrors('gstin');
    }

    #[Test]
    public function update_rejects_invalid_udyam_number(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'         => 'Test Vendor',
                'category'     => 'micro',
                'udyam_number' => 'BAD-FORMAT',
            ])
            ->assertSessionHasErrors('udyam_number');
    }

    #[Test]
    public function update_requires_name(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Vendor',
            'category'  => 'micro',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->put(route('vendors.update', $vendor), [
                'name'     => '',
                'category' => 'micro',
            ])
            ->assertSessionHasErrors('name');
    }

    // ─── Bulk Classify ────────────────────────────────────────────────────────

    #[Test]
    public function bulk_classify_reclassifies_selected_vendors(): void
    {
        $v1 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V1', 'category' => 'unclassified', 'is_active' => true]);
        $v2 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V2', 'category' => 'unclassified', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('vendors.bulk-classify'), [
                'vendor_ids' => [$v1->id, $v2->id],
                'category'   => 'micro',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendors', ['id' => $v1->id, 'category' => 'micro']);
        $this->assertDatabaseHas('vendors', ['id' => $v2->id, 'category' => 'micro']);
    }

    #[Test]
    public function bulk_classify_dispatches_propagation_jobs(): void
    {
        $v1 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V1', 'category' => 'unclassified', 'is_active' => true]);
        $v2 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V2', 'category' => 'unclassified', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('vendors.bulk-classify'), [
                'vendor_ids' => [$v1->id, $v2->id],
                'category'   => 'small',
            ]);

        Bus::assertDispatchedTimes(PropagateVendorClassification::class, 2);
    }

    #[Test]
    public function bulk_classify_rejects_more_than_100_vendors(): void
    {
        $ids = range(1, 101);

        $this->actingAs($this->user)
            ->post(route('vendors.bulk-classify'), [
                'vendor_ids' => $ids,
                'category'   => 'micro',
            ])
            ->assertSessionHasErrors('vendor_ids');
    }

    #[Test]
    public function bulk_classify_requires_category(): void
    {
        $v1 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V1', 'category' => 'unclassified', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('vendors.bulk-classify'), [
                'vendor_ids' => [$v1->id],
                'category'   => '',
            ])
            ->assertSessionHasErrors('category');
    }

    #[Test]
    public function bulk_classify_skips_vendors_with_same_category(): void
    {
        $v1 = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'V1', 'category' => 'micro', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('vendors.bulk-classify'), [
                'vendor_ids' => [$v1->id],
                'category'   => 'micro', // same category
            ]);

        // Job should NOT be dispatched since category didn't change
        Bus::assertNotDispatched(PropagateVendorClassification::class);
    }
}
