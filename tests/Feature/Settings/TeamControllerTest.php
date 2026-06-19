<?php

namespace Tests\Feature\Settings;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Enums\VendorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Team Test Corp',
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

    // ─── Store ────────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_add_team_member(): void
    {
        $this->actingAs($this->owner)
            ->post('/settings/team', [
                'name'     => 'Finance User',
                'email'    => 'finance@corp.com',
                'role'     => UserRole::Finance->value,
                'password' => 'SecurePass1!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email'     => 'finance@corp.com',
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
        ]);
    }

    #[Test]
    public function cannot_add_user_beyond_plan_limit(): void
    {
        // Starter plan = 5 users; owner is already 1, create 4 more
        for ($i = 2; $i <= 5; $i++) {
            User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'role'      => UserRole::Finance->value,
                'is_active' => true,
            ]);
        }

        $this->actingAs($this->owner)
            ->post('/settings/team', [
                'name'     => 'Extra User',
                'email'    => 'extra@corp.com',
                'role'     => UserRole::Finance->value,
                'password' => 'SecurePass1!',
            ])
            ->assertSessionHasErrors();
    }

    #[Test]
    public function finance_user_cannot_add_team_members(): void
    {
        $finance = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post('/settings/team', [
                'name'     => 'New User',
                'email'    => 'new@corp.com',
                'role'     => UserRole::Finance->value,
                'password' => 'SecurePass1!',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function email_must_be_unique_across_all_users(): void
    {
        User::factory()->create([
            'email'     => 'existing@corp.com',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->owner)
            ->post('/settings/team', [
                'name'     => 'Another',
                'email'    => 'existing@corp.com',
                'role'     => UserRole::Finance->value,
                'password' => 'SecurePass1!',
            ])
            ->assertSessionHasErrors('email');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_change_team_member_role(): void
    {
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->put("/settings/team/{$member->id}", [
                'role' => UserRole::Admin->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id'   => $member->id,
            'role' => UserRole::Admin->value,
        ]);
    }

    #[Test]
    public function cannot_update_user_from_different_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role'      => UserRole::Finance->value,
        ]);

        $this->actingAs($this->owner)
            ->put("/settings/team/{$otherUser->id}", ['role' => UserRole::Admin->value])
            ->assertForbidden();
    }

    // ─── Destroy (deactivate) ─────────────────────────────────────────────────

    #[Test]
    public function owner_can_deactivate_team_member(): void
    {
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Finance->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->delete("/settings/team/{$member->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $member->id, 'is_active' => false]);
    }

    #[Test]
    public function owner_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->owner)
            ->delete("/settings/team/{$this->owner->id}")
            ->assertSessionHasErrors();
    }

    #[Test]
    public function cannot_deactivate_user_from_different_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name'                => 'Other Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->delete("/settings/team/{$otherUser->id}")
            ->assertForbidden();
    }
}
