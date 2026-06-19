<?php

namespace Tests\Unit\Services;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant          $tenant;
    private PlanLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                => 'Test Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Trial->value,
            'trial_ends_at'       => now()->addDays(14),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->service = app(PlanLimitService::class);
    }

    // ─── Vendor limits ────────────────────────────────────────────────────────

    #[Test]
    public function starter_plan_allows_vendors_up_to_limit(): void
    {
        $this->createVendors(49);

        $this->assertTrue($this->service->canAddVendor($this->tenant));
    }

    #[Test]
    public function starter_plan_blocks_vendor_beyond_limit(): void
    {
        $this->createVendors(50);

        $this->assertFalse($this->service->canAddVendor($this->tenant));
    }

    #[Test]
    public function growth_plan_allows_200_vendors(): void
    {
        $this->tenant->update(['plan' => TenantPlan::Growth->value]);
        $this->createVendors(199);

        $this->assertTrue($this->service->canAddVendor($this->tenant));
    }

    #[Test]
    public function growth_plan_blocks_201st_vendor(): void
    {
        $this->tenant->update(['plan' => TenantPlan::Growth->value]);
        $this->createVendors(200);

        $this->assertFalse($this->service->canAddVendor($this->tenant));
    }

    #[Test]
    public function ca_firm_plan_is_unlimited_for_vendors(): void
    {
        $this->tenant->update(['plan' => TenantPlan::CaFirm->value]);
        $this->createVendors(500);

        $this->assertTrue($this->service->canAddVendor($this->tenant));
    }

    #[Test]
    public function soft_deleted_vendors_do_not_count_toward_limit(): void
    {
        // Create 50 vendors and soft-delete one
        $this->createVendors(50);
        Vendor::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->first()
            ->delete();

        // Now only 49 active — should be able to add
        $this->assertTrue($this->service->canAddVendor($this->tenant));
    }

    // ─── User limits ──────────────────────────────────────────────────────────

    #[Test]
    public function starter_plan_allows_users_up_to_limit(): void
    {
        $this->createUsers(4);

        $this->assertTrue($this->service->canAddUser($this->tenant));
    }

    #[Test]
    public function starter_plan_blocks_user_beyond_limit(): void
    {
        $this->createUsers(5);

        $this->assertFalse($this->service->canAddUser($this->tenant));
    }

    #[Test]
    public function ca_firm_plan_is_unlimited_for_users(): void
    {
        $this->tenant->update(['plan' => TenantPlan::CaFirm->value]);
        $this->createUsers(50);

        $this->assertTrue($this->service->canAddUser($this->tenant));
    }

    // ─── Count accuracy ───────────────────────────────────────────────────────

    #[Test]
    public function current_vendor_count_returns_accurate_count(): void
    {
        $this->createVendors(7);

        $this->assertEquals(7, $this->service->currentVendorCount($this->tenant));
    }

    #[Test]
    public function current_user_count_excludes_inactive_users(): void
    {
        $this->createUsers(3);
        User::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->first()
            ->update(['is_active' => false]);

        // Only 2 active users counted
        $this->assertEquals(2, $this->service->currentUserCount($this->tenant));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createVendors(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Vendor::create([
                'tenant_id' => $this->tenant->id,
                'name'      => "Vendor {$i}",
                'category'  => VendorCategory::Micro->value,
                'is_active' => true,
            ]);
        }
    }

    private function createUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            User::create([
                'tenant_id' => $this->tenant->id,
                'name'      => "User {$i}",
                'email'     => "user{$i}@test{$this->tenant->id}.com",
                'password'  => bcrypt('password'),
                'role'      => UserRole::Finance->value,
                'is_active' => true,
            ]);
        }
    }
}
