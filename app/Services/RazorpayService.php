<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayService
{
    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $this->keyId         = (string) (config('services.razorpay.key_id') ?? '');
        $this->keySecret     = (string) (config('services.razorpay.key_secret') ?? '');
        $this->webhookSecret = (string) (config('services.razorpay.webhook_secret') ?? '');
    }

    /**
     * Verify the HMAC-SHA256 signature from Razorpay webhook headers.
     * Razorpay sends: X-Razorpay-Signature: HMAC(webhook_secret, raw_body)
     *
     * Must be called on the RAW request body before JSON parsing.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Create or retrieve a Razorpay customer for the given tenant.
     * Idempotent — returns existing customer ID if already created.
     */
    public function createCustomer(Tenant $tenant): string
    {
        if ($tenant->razorpay_customer_id) {
            return $tenant->razorpay_customer_id;
        }

        $response = $this->post('/customers', [
            'name'    => $tenant->name,
            'email'   => $tenant->email,
            'contact' => $tenant->phone ?? '',
            'notes'   => ['tenant_id' => $tenant->id],
        ]);

        $customerId = $response['id'];
        $tenant->update(['razorpay_customer_id' => $customerId]);

        return $customerId;
    }

    /**
     * Create a Razorpay subscription for the given plan.
     * Returns the subscription object array from Razorpay.
     */
    public function createSubscription(Tenant $tenant, string $planKey): array
    {
        $planId = config("services.razorpay.plans.{$planKey}");
        if (! $planId) {
            throw new RuntimeException("Unknown plan key: {$planKey}");
        }

        $customerId = $this->createCustomer($tenant);

        return $this->post('/subscriptions', [
            'plan_id'          => $planId,
            'customer_id'      => $customerId,
            'total_count'      => 120, // 10-year max; effectively recurring
            'quantity'         => 1,
            'customer_notify'  => 1,
            'notes'            => [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
        ]);
    }

    /**
     * Fetch the current status of a Razorpay subscription.
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->get("/subscriptions/{$subscriptionId}");
    }

    /**
     * Cancel a subscription immediately.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->post("/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_cycle_end' => 0,
        ]);
    }

    // ── Private HTTP helpers ───────────────────────────────────────────────────

    private function post(string $path, array $data): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->post($this->baseUrl . $path, $data);

        return $this->handleResponse($response, 'POST', $path);
    }

    private function get(string $path): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->get($this->baseUrl . $path);

        return $this->handleResponse($response, 'GET', $path);
    }

    private function handleResponse(Response $response, string $method, string $path): array
    {
        if ($response->failed()) {
            $body = $response->json() ?? [];
            $error = $body['error']['description'] ?? "HTTP {$response->status()}";
            Log::error("Razorpay API error: {$method} {$path} → {$error}", ['body' => $body]);
            throw new RuntimeException("Razorpay error: {$error}");
        }

        return $response->json();
    }
}
