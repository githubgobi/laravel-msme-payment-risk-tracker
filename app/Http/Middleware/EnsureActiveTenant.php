<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access for tenants whose trial has expired or whose account is
 * suspended/inactive. Renders an Inertia page instead of a hard error.
 *
 * Super-admin users (tenant_id = null) are always allowed through.
 */
class EnsureActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not authenticated or super-admin (no tenant) — pass through
        if (! $user || ! $user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant || ! $tenant->hasActiveAccess()) {
            $isTrial  = $tenant?->subscription_status === TenantStatus::Trial;
            $isActive = $tenant?->subscription_status === TenantStatus::Active;

            $reason = match(true) {
                $isTrial  => 'trial_expired',
                $isActive => 'subscription_expired',
                default   => 'account_suspended',
            };

            return Inertia::render('Auth/Suspended', [
                'reason'       => $reason,
                'plan'         => $tenant?->plan?->label(),
                'trial_ends_at' => $tenant?->trial_ends_at?->toDateString(),
                'status'       => $tenant?->subscription_status?->label(),
            ])->toResponse($request)->setStatusCode(402);
        }

        return $next($request);
    }
}
