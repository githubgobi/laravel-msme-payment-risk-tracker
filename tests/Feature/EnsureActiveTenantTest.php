<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureActiveTenantTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_tenant_with_future_subscription_passes_through(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status'  => TenantStatus::Active->value,
            'subscription_ends_at' => now()->addYear(),
            'is_active'            => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    #[Test]
    public function trial_tenant_with_future_trial_end_passes_through(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => TenantStatus::Trial->value,
            'trial_ends_at'       => now()->addDays(7),
            'is_active'           => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    #[Test]
    public function trial_tenant_with_expired_trial_returns_402(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => TenantStatus::Trial->value,
            'trial_ends_at'       => now()->subDay(),
            'is_active'           => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(402);
    }

    #[Test]
    public function active_tenant_with_expired_subscription_returns_402(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status'  => TenantStatus::Active->value,
            'subscription_ends_at' => now()->subDay(),
            'is_active'            => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(402);
    }

    #[Test]
    public function inactive_tenant_returns_402(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => TenantStatus::Inactive->value,
            'is_active'           => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(402);
    }

    #[Test]
    public function suspended_tenant_returns_402(): void
    {
        $tenant = $this->makeTenant([
            'subscription_status' => TenantStatus::Suspended->value,
            'is_active'           => true,
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)->get('/dashboard')->assertStatus(402);
    }

    #[Test]
    public function super_admin_without_tenant_passes_through(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email'     => 'superadmin@msme.local',
            'password'  => bcrypt('admin123'),
        ]);

        $this->actingAs($superAdmin)->get('/dashboard')->assertStatus(200);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeTenant(array $overrides): Tenant
    {
        return Tenant::create(array_merge([
            'name'                    => 'Test Corp',
            'plan'                    => TenantPlan::Starter->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ], $overrides));
    }

    private function makeOwner(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }
}
