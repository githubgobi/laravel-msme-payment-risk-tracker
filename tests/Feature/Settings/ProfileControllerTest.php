<?php

namespace Tests\Feature\Settings;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Profile Test Corp',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'subscription_ends_at'    => now()->addYear(),
            'onboarding_completed_at' => now(),
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Test]
    public function guest_is_redirected_from_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    #[Test]
    public function settings_page_renders_with_required_props(): void
    {
        $this->actingAs($this->owner)
            ->get('/settings')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Settings/Index')
                ->has('profile')
                ->has('billing')
                ->has('team')
                ->has('limits')
                ->has('canManage')
            );
    }

    #[Test]
    public function limits_reflect_current_plan(): void
    {
        $this->actingAs($this->owner)
            ->get('/settings')
            ->assertInertia(fn (Assert $p) => $p
                ->where('limits.vendors_max', 50)
                ->where('limits.users_max', 5)
                ->where('limits.users_used', 1)
            );
    }

    #[Test]
    public function owner_has_can_manage_true(): void
    {
        $this->actingAs($this->owner)
            ->get('/settings')
            ->assertInertia(fn (Assert $p) => $p->where('canManage', true));
    }

    #[Test]
    public function finance_user_has_can_manage_false(): void
    {
        $financeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($financeUser)
            ->get('/settings')
            ->assertInertia(fn (Assert $p) => $p->where('canManage', false));
    }

    // ─── Update profile ───────────────────────────────────────────────────────

    #[Test]
    public function owner_can_update_business_profile(): void
    {
        $this->actingAs($this->owner)
            ->put('/settings/profile', [
                'name'          => 'Updated Corp Name',
                'rbi_bank_rate' => 7.00,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'id'            => $this->tenant->id,
            'name'          => 'Updated Corp Name',
            'rbi_bank_rate' => 7.00,
        ]);
    }

    #[Test]
    public function finance_user_cannot_update_profile(): void
    {
        $financeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($financeUser)
            ->put('/settings/profile', ['name' => 'Hacked Name'])
            ->assertForbidden();
    }

    #[Test]
    public function rbi_bank_rate_must_be_between_1_and_25(): void
    {
        $this->actingAs($this->owner)
            ->put('/settings/profile', [
                'name'          => 'Corp',
                'rbi_bank_rate' => 99,
            ])
            ->assertSessionHasErrors('rbi_bank_rate');
    }

    #[Test]
    public function gstin_must_be_unique_across_tenants(): void
    {
        $other = Tenant::create([
            'name'                => 'Other Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'gstin'               => '29AADCB2230M1Z3',
            'is_active'           => true,
        ]);

        $this->actingAs($this->owner)
            ->put('/settings/profile', [
                'name'  => 'Corp',
                'gstin' => '29AADCB2230M1Z3',
            ])
            ->assertSessionHasErrors('gstin');
    }

    #[Test]
    public function own_gstin_passes_unique_validation(): void
    {
        $this->tenant->update(['gstin' => '29AADCB2230M1Z3']);

        $this->actingAs($this->owner)
            ->put('/settings/profile', [
                'name'  => 'Updated Name',
                'gstin' => '29AADCB2230M1Z3',
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function cross_tenant_isolation_on_settings(): void
    {
        $otherTenant = Tenant::create([
            'name'                    => 'Other Corp',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);
        $otherOwner = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role'      => UserRole::Owner->value,
        ]);

        $this->actingAs($otherOwner)
            ->get('/settings')
            ->assertInertia(fn (Assert $p) => $p
                ->where('profile.name', 'Other Corp')
            );
    }
}
