<?php

namespace Tests\Unit\Services;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class RazorpayServiceTest extends TestCase
{
    use RefreshDatabase;

    private RazorpayService $service;
    private Tenant          $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id'         => 'rzp_test_key',
            'services.razorpay.key_secret'      => 'rzp_test_secret',
            'services.razorpay.webhook_secret'  => 'webhook-secret-here',
            'services.razorpay.plans.starter'   => 'plan_starter_id',
        ]);

        $this->service = new RazorpayService();

        $this->tenant = Tenant::create([
            'name'                => 'Test Company',
            'email'               => 'test@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Trial->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);
    }

    #[Test]
    public function verify_webhook_signature_returns_true_for_valid_signature(): void
    {
        $payload   = '{"event":"subscription.activated"}';
        $signature = hash_hmac('sha256', $payload, 'webhook-secret-here');

        $this->assertTrue($this->service->verifyWebhookSignature($payload, $signature));
    }

    #[Test]
    public function verify_webhook_signature_returns_false_for_tampered_payload(): void
    {
        $original  = '{"event":"subscription.activated"}';
        $tampered  = '{"event":"subscription.activated","extra":"injected"}';
        $signature = hash_hmac('sha256', $original, 'webhook-secret-here');

        $this->assertFalse($this->service->verifyWebhookSignature($tampered, $signature));
    }

    #[Test]
    public function verify_webhook_signature_returns_false_for_empty_secret(): void
    {
        config(['services.razorpay.webhook_secret' => '']);
        $service   = new RazorpayService();
        $payload   = '{"event":"test"}';
        $signature = hash_hmac('sha256', $payload, 'any-secret');

        $this->assertFalse($service->verifyWebhookSignature($payload, $signature));
    }

    #[Test]
    public function create_customer_calls_razorpay_and_stores_id(): void
    {
        Http::fake([
            'api.razorpay.com/v1/customers' => Http::response(['id' => 'cust_abc123'], 200),
        ]);

        $customerId = $this->service->createCustomer($this->tenant);

        $this->assertEquals('cust_abc123', $customerId);
        $this->assertEquals('cust_abc123', $this->tenant->fresh()->razorpay_customer_id);
    }

    #[Test]
    public function create_customer_is_idempotent_when_already_set(): void
    {
        $this->tenant->update(['razorpay_customer_id' => 'cust_existing']);

        Http::fake(); // should not be called

        $customerId = $this->service->createCustomer($this->tenant);

        $this->assertEquals('cust_existing', $customerId);
        Http::assertNothingSent();
    }

    #[Test]
    public function create_subscription_calls_razorpay_with_plan_id(): void
    {
        Http::fake([
            'api.razorpay.com/v1/customers'     => Http::response(['id' => 'cust_x'], 200),
            'api.razorpay.com/v1/subscriptions' => Http::response([
                'id'      => 'sub_new123',
                'plan_id' => 'plan_starter_id',
            ], 200),
        ]);

        $subscription = $this->service->createSubscription($this->tenant, 'starter');

        $this->assertEquals('sub_new123', $subscription['id']);
        $this->assertEquals('plan_starter_id', $subscription['plan_id']);
    }

    #[Test]
    public function create_subscription_throws_for_unknown_plan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown plan key/');

        $this->service->createSubscription($this->tenant, 'nonexistent');
    }

    #[Test]
    public function throws_runtime_exception_on_razorpay_api_error(): void
    {
        Http::fake([
            'api.razorpay.com/v1/customers' => Http::response([
                'error' => ['description' => 'Invalid API key'],
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Razorpay error/');

        $this->service->createCustomer($this->tenant);
    }
}
