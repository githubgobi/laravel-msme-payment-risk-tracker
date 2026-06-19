<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Show page ────────────────────────────────────────────────────────────

    #[Test]
    public function registration_page_renders_for_guest(): void
    {
        $this->get('/register')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $p) => $p->component('Auth/Register'));
    }

    #[Test]
    public function logged_in_user_is_redirected_away_from_register(): void
    {
        $tenant = $this->createTenant();
        $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Owner->value]);

        $this->actingAs($user)->get('/register')->assertRedirect('/dashboard');
    }

    // ─── Successful registration ──────────────────────────────────────────────

    #[Test]
    public function successful_registration_creates_tenant_and_user(): void
    {
        $this->post('/register', [
            'business_name'         => 'Ramesh Traders Pvt Ltd',
            'name'                  => 'Ramesh Agarwal',
            'email'                 => 'ramesh@ramesht.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('tenants', [
            'name'                => 'Ramesh Traders Pvt Ltd',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Trial->value,
        ]);

        $this->assertDatabaseHas('users', [
            'name'  => 'Ramesh Agarwal',
            'email' => 'ramesh@ramesht.com',
            'role'  => UserRole::Owner->value,
        ]);
    }

    #[Test]
    public function registration_starts_14_day_trial(): void
    {
        $this->post('/register', [
            'business_name'         => 'Trial Business',
            'name'                  => 'Owner',
            'email'                 => 'owner@trial.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $tenant = Tenant::where('name', 'Trial Business')->first();

        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        // Allow 13-14 due to sub-second execution time drift
        $this->assertGreaterThanOrEqual(13, (int) now()->diffInDays($tenant->trial_ends_at));
    }

    #[Test]
    public function registration_logs_user_in_automatically(): void
    {
        $this->post('/register', [
            'business_name'         => 'Auto Login Corp',
            'name'                  => 'Test Owner',
            'email'                 => 'owner@autologin.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $this->assertAuthenticated();
    }

    #[Test]
    public function optional_gstin_stored_when_provided(): void
    {
        $this->post('/register', [
            'business_name'         => 'GST Business',
            'name'                  => 'GST Owner',
            'email'                 => 'gst@gstbiz.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'gstin'                 => '29AADCB2230M1Z3',
        ]);

        $this->assertDatabaseHas('tenants', ['gstin' => '29AADCB2230M1Z3']);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    #[Test]
    public function business_name_is_required(): void
    {
        $this->post('/register', [
            'name'                  => 'Owner',
            'email'                 => 'owner@test.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertSessionHasErrors('business_name');
    }

    #[Test]
    public function email_must_be_unique(): void
    {
        $tenant = $this->createTenant();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'existing@test.com']);

        $this->post('/register', [
            'business_name'         => 'New Corp',
            'name'                  => 'Owner',
            'email'                 => 'existing@test.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    public function password_must_match_confirmation(): void
    {
        $this->post('/register', [
            'business_name'         => 'Corp',
            'name'                  => 'Owner',
            'email'                 => 'owner@corp.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'DifferentPass!',
        ])->assertSessionHasErrors('password');
    }

    #[Test]
    public function invalid_gstin_is_rejected(): void
    {
        $this->post('/register', [
            'business_name'         => 'Corp',
            'name'                  => 'Owner',
            'email'                 => 'owner@corp.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'gstin'                 => 'INVALID',
        ])->assertSessionHasErrors('gstin');
    }

    #[Test]
    public function duplicate_gstin_is_rejected(): void
    {
        $this->createTenant(['gstin' => '29AADCB2230M1Z3']);

        $this->post('/register', [
            'business_name'         => 'Another Corp',
            'name'                  => 'Owner',
            'email'                 => 'owner2@corp.com',
            'password'              => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'gstin'                 => '29AADCB2230M1Z3',
        ])->assertSessionHasErrors('gstin');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name'                => 'Existing Corp',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ], $overrides));
    }
}
