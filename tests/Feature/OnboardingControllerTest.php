<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->onboarding()->create([
            'name'                => 'Test Corp',
            'email'               => 'corp@example.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Trial->value,
            'trial_ends_at'       => now()->addDays(14),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function onboarding_page_is_accessible_by_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->get('/onboarding');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Onboarding/Index')
                 ->has('steps')
                 ->has('tenantName')
                 ->has('allComplete')
        );
    }

    #[Test]
    public function onboarding_page_is_inaccessible_to_guests(): void
    {
        $response = $this->get('/onboarding');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function complete_sets_onboarding_completed_at(): void
    {
        $response = $this->actingAs($this->owner)
            ->post('/onboarding/complete');

        $response->assertRedirect('/dashboard');

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->onboarding_completed_at);
    }

    #[Test]
    public function complete_requires_authentication(): void
    {
        $response = $this->post('/onboarding/complete');

        $response->assertRedirect('/login');
        $this->tenant->refresh();
        $this->assertNull($this->tenant->onboarding_completed_at);
    }

    #[Test]
    public function onboarding_middleware_redirects_unfinished_tenant_from_dashboard(): void
    {
        // Tenant without onboarding → dashboard should redirect to /onboarding
        $response = $this->actingAs($this->owner)->get('/dashboard');

        $response->assertRedirect('/onboarding');
    }

    #[Test]
    public function onboarding_middleware_lets_completed_tenant_through(): void
    {
        $this->tenant->update(['onboarding_completed_at' => now()]);

        // Dashboard must NOT redirect to onboarding now
        $response = $this->actingAs($this->owner)->get('/dashboard');

        // 200 or 302 to another route is fine — anything but /onboarding
        $this->assertNotEquals('/onboarding', $response->headers->get('Location'));
    }

    #[Test]
    public function steps_include_expected_keys(): void
    {
        $response = $this->actingAs($this->owner)->get('/onboarding');

        $response->assertInertia(fn ($page) =>
            $page->has('steps', 5)
                 ->has('steps.0.key')
                 ->has('steps.0.title')
                 ->has('steps.0.done')
        );
    }
}
