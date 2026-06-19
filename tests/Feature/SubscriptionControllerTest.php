<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id'     => 'rzp_test_key_id',
            'services.razorpay.key_secret'  => 'rzp_test_key_secret',
            'services.razorpay.plans.starter'      => 'plan_starter123',
            'services.razorpay.plans.professional'  => 'plan_pro456',
            'services.razorpay.plans.enterprise'    => 'plan_ent789',
        ]);

        $this->tenant = Tenant::create([
            'name'                => 'Test Company',
            'email'               => 'test@example.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Trial->value,
            'trial_ends_at'       => now()->addDays(5),
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->owner = User::create([
            'name'      => 'Owner User',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
            'role'      => UserRole::Owner->value,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function upgrade_page_requires_auth(): void
    {
        $this->get(route('subscription.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function upgrade_page_renders_with_plan_catalog(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subscription.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Subscription/Upgrade')
                ->has('plans', 3)
                ->has('razorpayKeyId')
                ->where('currentPlan', TenantPlan::Starter->value)
                ->where('currentStatus', TenantStatus::Trial->value)
            );
    }

    #[Test]
    public function subscribe_returns_subscription_id_from_razorpay(): void
    {
        Http::fake([
            'api.razorpay.com/v1/customers' => Http::response([
                'id' => 'cust_test001',
            ], 200),
            'api.razorpay.com/v1/subscriptions' => Http::response([
                'id'      => 'sub_newtest001',
                'plan_id' => 'plan_starter123',
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('subscription.subscribe', 'starter'))
            ->assertOk()
            ->assertJsonFragment(['subscription_id' => 'sub_newtest001']);

        // Tenant should now have the subscription ID stored
        $this->assertEquals('sub_newtest001', $this->tenant->fresh()->razorpay_subscription_id);
    }

    #[Test]
    public function subscribe_returns_422_for_unknown_plan(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('subscription.subscribe', 'ultra'))
            ->assertUnprocessable();
    }

    #[Test]
    public function subscribe_returns_500_when_razorpay_fails(): void
    {
        Http::fake([
            'api.razorpay.com/*' => Http::response(['error' => ['description' => 'Server error']], 500),
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('subscription.subscribe', 'starter'))
            ->assertStatus(500);
    }
}
