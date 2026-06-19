<?php

namespace Tests\Unit\Services;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\RazorpayService;
use App\Services\TenantSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantSubscriptionService $service;
    private Tenant                     $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $razorpayMock  = $this->createMock(RazorpayService::class);
        $this->service = new TenantSubscriptionService($razorpayMock);

        $this->tenant = Tenant::create([
            'name'                    => 'Test Company',
            'email'                   => 'test@company.com',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Trial->value,
            'trial_ends_at'           => now()->addDays(14),
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'razorpay_subscription_id' => 'sub_test_abc',
        ]);
    }

    private function payload(string $event, array $entity = []): array
    {
        return [
            'event' => $event,
            'payload' => [
                'subscription' => [
                    'entity' => array_merge([
                        'id'          => 'sub_test_abc',
                        'plan_id'     => 'plan_starter',
                        'status'      => 'active',
                        'current_end' => now()->addMonth()->timestamp,
                    ], $entity),
                ],
            ],
        ];
    }

    #[Test]
    public function activated_event_transitions_tenant_to_active(): void
    {
        $this->service->handleWebhook($this->payload('subscription.activated'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Active, $this->tenant->subscription_status);
        $this->assertTrue($this->tenant->is_active);
        $this->assertNull($this->tenant->grace_period_ends_at);
    }

    #[Test]
    public function charged_event_extends_subscription_end_date(): void
    {
        $this->tenant->update(['subscription_status' => TenantStatus::Active->value]);
        $futureTimestamp = now()->addMonths(3)->timestamp;

        $this->service->handleWebhook($this->payload('subscription.charged', [
            'current_end' => $futureTimestamp,
        ]));

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->subscription_ends_at);
        $this->assertEqualsWithDelta($futureTimestamp, $this->tenant->subscription_ends_at->timestamp, 2);
    }

    #[Test]
    public function halted_event_sets_grace_period_of_7_days(): void
    {
        $this->tenant->update(['subscription_status' => TenantStatus::Active->value]);

        $this->service->handleWebhook($this->payload('subscription.halted'));

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->grace_period_ends_at);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $this->tenant->grace_period_ends_at->timestamp,
            60
        );
        // Status should remain Active during grace period
        $this->assertEquals(TenantStatus::Active, $this->tenant->subscription_status);
    }

    #[Test]
    public function cancelled_event_sets_tenant_to_expired(): void
    {
        $this->service->handleWebhook($this->payload('subscription.cancelled'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Inactive, $this->tenant->subscription_status);
        $this->assertNull($this->tenant->grace_period_ends_at);
    }

    #[Test]
    public function completed_event_sets_tenant_to_expired(): void
    {
        $this->service->handleWebhook($this->payload('subscription.completed'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Inactive, $this->tenant->subscription_status);
    }

    #[Test]
    public function returns_false_for_unrecognised_subscription_id(): void
    {
        $payload = $this->payload('subscription.activated', ['id' => 'sub_unknown']);

        $result = $this->service->handleWebhook($payload);

        $this->assertFalse($result);
        // Tenant should be unmodified
        $this->assertEquals(TenantStatus::Trial, $this->tenant->fresh()->subscription_status);
    }

    #[Test]
    public function returns_false_when_payload_has_no_subscription_entity(): void
    {
        $result = $this->service->handleWebhook(['event' => 'payment.captured', 'payload' => []]);

        $this->assertFalse($result);
    }
}
