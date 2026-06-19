<?php

namespace App\Http\Controllers;

use App\Services\RazorpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(private readonly RazorpayService $razorpay) {}

    /**
     * Show the subscription upgrade / plan selection page.
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Subscription/Upgrade', [
            'currentPlan'     => $tenant->plan->value,
            'currentStatus'   => $tenant->subscription_status->value,
            'trialEndsAt'     => $tenant->trial_ends_at?->toDateString(),
            'subscriptionEndsAt' => $tenant->subscription_ends_at?->toDateString(),
            'gracePeriodEndsAt'  => $tenant->grace_period_ends_at?->toDateString(),
            'razorpayKeyId'   => config('services.razorpay.key_id'),
            'plans'           => $this->planCatalog(),
        ]);
    }

    /**
     * Initiate a subscription for the given plan.
     * Creates a Razorpay subscription and returns its details to the frontend
     * so the Razorpay JS checkout modal can be launched.
     */
    public function subscribe(Request $request, string $plan): \Illuminate\Http\JsonResponse
    {
        $validPlans = ['starter', 'professional', 'enterprise'];
        if (! in_array($plan, $validPlans, true)) {
            return response()->json(['error' => 'Invalid plan.'], 422);
        }

        $tenant = $request->user()->tenant;

        try {
            $subscription = $this->razorpay->createSubscription($tenant, $plan);

            $tenant->update([
                'razorpay_subscription_id' => $subscription['id'],
                'razorpay_plan_id'          => $subscription['plan_id'] ?? null,
            ]);

            return response()->json([
                'subscription_id' => $subscription['id'],
                'razorpay_key_id' => config('services.razorpay.key_id'),
                'name'            => $tenant->name,
                'email'           => $tenant->email,
                'contact'         => $tenant->phone ?? '',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Unable to create subscription. Please try again.'], 500);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function planCatalog(): array
    {
        return [
            [
                'key'        => 'starter',
                'name'       => 'Starter',
                'price'      => 1500,
                'price_text' => '₹1,500/month',
                'vendors'    => 50,
                'users'      => 3,
                'features'   => [
                    'Up to 50 MSME vendors',
                    '3 team members',
                    'Daily risk recompute',
                    'Email alerts',
                    'Excel import/export',
                    'CA Portal access',
                ],
            ],
            [
                'key'        => 'professional',
                'name'       => 'Professional',
                'price'      => 3000,
                'price_text' => '₹3,000/month',
                'vendors'    => 200,
                'users'      => 10,
                'features'   => [
                    'Up to 200 MSME vendors',
                    '10 team members',
                    'Everything in Starter',
                    'WhatsApp alerts',
                    'Udyam API verification',
                    'Vendor risk scoring',
                ],
            ],
            [
                'key'        => 'enterprise',
                'name'       => 'Enterprise',
                'price'      => 4000,
                'price_text' => '₹4,000/month',
                'vendors'    => PHP_INT_MAX,
                'users'      => PHP_INT_MAX,
                'features'   => [
                    'Unlimited vendors',
                    'Unlimited team members',
                    'Everything in Professional',
                    'Dedicated onboarding',
                    'Priority support',
                    'Custom integrations on request',
                ],
            ],
        ];
    }
}
