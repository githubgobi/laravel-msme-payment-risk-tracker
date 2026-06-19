<?php

namespace Tests\Feature;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $webhookSecret = 'test-webhook-secret-32chars-here!!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay.webhook_secret' => $this->webhookSecret]);

        $this->tenant = Tenant::create([
            'name'                    => 'Acme Corp',
            'email'                   => 'acme@example.com',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Trial->value,
            'trial_ends_at'           => now()->addDays(14),
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'razorpay_subscription_id' => 'sub_test123',
        ]);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }

    private function webhook(array $data, ?string $overrideSignature = null): \Illuminate\Testing\TestResponse
    {
        $payload   = json_encode($data);
        $signature = $overrideSignature ?? $this->sign($payload);

        return $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
            'CONTENT_TYPE'              => 'application/json',
        ], $payload);
    }

    private function subscriptionPayload(string $event, array $entityOverrides = []): array
    {
        return [
            'event' => $event,
            'payload' => [
                'subscription' => [
                    'entity' => array_merge([
                        'id'          => 'sub_test123',
                        'plan_id'     => 'plan_starter',
                        'status'      => 'active',
                        'current_end' => now()->addMonth()->timestamp,
                    ], $entityOverrides),
                ],
            ],
        ];
    }

    #[Test]
    public function rejects_invalid_signature(): void
    {
        $this->webhook(
            $this->subscriptionPayload('subscription.activated'),
            'bad-signature'
        )->assertStatus(403);
    }

    #[Test]
    public function returns_200_for_valid_signature(): void
    {
        $this->webhook($this->subscriptionPayload('subscription.activated'))
            ->assertOk()
            ->assertSee('OK');
    }

    #[Test]
    public function subscription_activated_sets_tenant_active(): void
    {
        $this->webhook($this->subscriptionPayload('subscription.activated'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Active, $this->tenant->subscription_status);
        $this->assertNull($this->tenant->grace_period_ends_at);
    }

    #[Test]
    public function subscription_charged_renews_subscription_ends_at(): void
    {
        $this->tenant->update(['subscription_status' => TenantStatus::Active->value]);
        $futureTimestamp = now()->addMonths(2)->timestamp;

        $this->webhook($this->subscriptionPayload('subscription.charged', [
            'current_end' => $futureTimestamp,
        ]));

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->subscription_ends_at);
        $this->assertNull($this->tenant->grace_period_ends_at);
    }

    #[Test]
    public function subscription_halted_grants_7_day_grace_period(): void
    {
        $this->tenant->update(['subscription_status' => TenantStatus::Active->value]);

        $this->webhook($this->subscriptionPayload('subscription.halted'));

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->grace_period_ends_at);
        // Grace period should be approximately 7 days from now
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $this->tenant->grace_period_ends_at->timestamp,
            60
        );
    }

    #[Test]
    public function subscription_cancelled_sets_tenant_expired(): void
    {
        $this->webhook($this->subscriptionPayload('subscription.cancelled'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Inactive, $this->tenant->subscription_status);
    }

    #[Test]
    public function subscription_completed_sets_tenant_expired(): void
    {
        $this->webhook($this->subscriptionPayload('subscription.completed'));

        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Inactive, $this->tenant->subscription_status);
    }

    #[Test]
    public function returns_200_for_unknown_event(): void
    {
        // Unknown events must still return 200 so Razorpay does not retry
        $this->webhook($this->subscriptionPayload('payment.captured'))
            ->assertOk();
    }

    #[Test]
    public function returns_200_when_no_tenant_matches_subscription_id(): void
    {
        $payload = $this->subscriptionPayload('subscription.activated', ['id' => 'sub_unknown999']);

        $this->webhook($payload)->assertOk();
        // Tenant should NOT be modified
        $this->tenant->refresh();
        $this->assertEquals(TenantStatus::Trial, $this->tenant->subscription_status);
    }
}
