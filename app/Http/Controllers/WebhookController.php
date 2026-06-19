<?php

namespace App\Http\Controllers;

use App\Services\RazorpayService;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles inbound webhooks from Razorpay.
 *
 * Route: POST /webhooks/razorpay
 * Excluded from CSRF via bootstrap/app.php csrf()->except()
 * Excluded from auth middleware (publicly reachable by Razorpay's servers)
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly RazorpayService $razorpay,
        private readonly TenantSubscriptionService $subscriptions,
    ) {}

    public function razorpay(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $this->razorpay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook: invalid signature', ['ip' => $request->ip()]);
            return response('Unauthorized', 403);
        }

        $data  = json_decode($payload, true) ?? [];
        $event = $data['event'] ?? 'unknown';

        Log::info("Razorpay webhook received: {$event}");

        $handled = $this->subscriptions->handleWebhook($data);

        if (! $handled) {
            Log::debug("Razorpay webhook ignored (no handler): {$event}");
        }

        // Always return 200 so Razorpay does not retry
        return response('OK', 200);
    }
}
