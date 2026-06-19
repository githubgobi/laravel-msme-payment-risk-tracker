<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handles Razorpay webhook events and syncs tenant subscription state.
 *
 * Razorpay lifecycle:
 *   subscription.activated  → tenant goes Active, grace_period cleared
 *   subscription.charged    → renew subscription_ends_at for another billing cycle
 *   subscription.halted     → payment failed; grant 7-day grace period
 *   subscription.cancelled  → tenant moved to Expired; access ends on subscription_ends_at
 *   subscription.completed  → same as cancelled (all cycles exhausted)
 */
class TenantSubscriptionService
{
    private const GRACE_PERIOD_DAYS = 7;

    public function __construct(private readonly RazorpayService $razorpay) {}

    /**
     * Route a webhook payload to the correct handler.
     * Returns true if the event was handled, false if unknown/ignored.
     */
    public function handleWebhook(array $payload): bool
    {
        $event  = $payload['event'] ?? '';
        $entity = $payload['payload']['subscription']['entity'] ?? null;

        if (! $entity) {
            Log::warning('Razorpay webhook missing subscription entity', ['event' => $event]);
            return false;
        }

        $subscriptionId = $entity['id'] ?? null;
        if (! $subscriptionId) {
            return false;
        }

        $tenant = Tenant::where('razorpay_subscription_id', $subscriptionId)->first();
        if (! $tenant) {
            // May arrive before we stored the subscription ID (race condition on activation)
            Log::warning('Razorpay webhook: no tenant for subscription', [
                'subscription_id' => $subscriptionId,
                'event'           => $event,
            ]);
            return false;
        }

        return match ($event) {
            'subscription.activated'  => $this->onActivated($tenant, $entity),
            'subscription.charged'    => $this->onCharged($tenant, $entity),
            'subscription.halted'     => $this->onHalted($tenant),
            'subscription.cancelled'  => $this->onCancelled($tenant, $entity),
            'subscription.completed'  => $this->onCancelled($tenant, $entity),
            default => false,
        };
    }

    /**
     * Sync all tenant subscriptions from Razorpay (used by the scheduler).
     * Only queries tenants that have an active subscription ID.
     */
    public function syncAll(): int
    {
        $synced = 0;

        Tenant::whereNotNull('razorpay_subscription_id')->each(function (Tenant $tenant) use (&$synced) {
            try {
                $sub = $this->razorpay->getSubscription($tenant->razorpay_subscription_id);
                $this->syncFromRazorpayData($tenant, $sub);
                $synced++;
            } catch (\Throwable $e) {
                Log::error("Failed to sync subscription for tenant {$tenant->id}: {$e->getMessage()}");
            }
        });

        return $synced;
    }

    // ── Event handlers ─────────────────────────────────────────────────────────

    private function onActivated(Tenant $tenant, array $entity): bool
    {
        $endsAt = isset($entity['current_end']) ? Carbon::createFromTimestamp($entity['current_end']) : null;

        $tenant->update([
            'subscription_status'      => TenantStatus::Active,
            'subscription_ends_at'     => $endsAt,
            'razorpay_plan_id'         => $entity['plan_id'] ?? $tenant->razorpay_plan_id,
            'grace_period_ends_at'     => null,
            'is_active'                => true,
        ]);

        Log::info("Tenant {$tenant->id} subscription activated via webhook");
        return true;
    }

    private function onCharged(Tenant $tenant, array $entity): bool
    {
        // A successful charge extends the billing period
        $endsAt = isset($entity['current_end']) ? Carbon::createFromTimestamp($entity['current_end']) : null;

        $tenant->update([
            'subscription_status'  => TenantStatus::Active,
            'subscription_ends_at' => $endsAt,
            'grace_period_ends_at' => null,
        ]);

        Log::info("Tenant {$tenant->id} subscription renewed to {$endsAt?->toDateString()}");
        return true;
    }

    private function onHalted(Tenant $tenant): bool
    {
        // Payment failed — grant a grace period before suspending
        $graceEndsAt = Carbon::now()->addDays(self::GRACE_PERIOD_DAYS);

        $tenant->update([
            'grace_period_ends_at' => $graceEndsAt,
        ]);

        Log::warning("Tenant {$tenant->id} payment halted; grace period until {$graceEndsAt->toDateString()}");
        return true;
    }

    private function onCancelled(Tenant $tenant, array $entity): bool
    {
        // Access continues until the current billing period ends
        $endsAt = isset($entity['current_end']) ? Carbon::createFromTimestamp($entity['current_end']) : now();

        $tenant->update([
            'subscription_status'  => TenantStatus::Inactive,
            'subscription_ends_at' => $endsAt,
            'grace_period_ends_at' => null,
        ]);

        Log::info("Tenant {$tenant->id} subscription cancelled; access until {$endsAt->toDateString()}");
        return true;
    }

    private function syncFromRazorpayData(Tenant $tenant, array $sub): void
    {
        $razorpayStatus = $sub['status'] ?? '';
        $endsAt = isset($sub['current_end']) ? Carbon::createFromTimestamp($sub['current_end']) : null;

        $tenantStatus = match ($razorpayStatus) {
            'active', 'authenticated' => TenantStatus::Active,
            'halted'                  => $tenant->subscription_status, // keep current; webhook handles this
            'cancelled', 'completed', 'expired' => TenantStatus::Inactive,
            default                   => $tenant->subscription_status,
        };

        $tenant->update([
            'subscription_status'  => $tenantStatus,
            'subscription_ends_at' => $endsAt,
        ]);
    }
}
