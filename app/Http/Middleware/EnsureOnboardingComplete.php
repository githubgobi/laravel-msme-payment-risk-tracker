<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects new tenants to /onboarding if they haven't completed setup.
 *
 * Applied on the authenticated + tenant.active middleware group.
 * Skipped for: super-admins (no tenant), the onboarding route itself,
 * logout, and impersonation routes.
 */
class EnsureOnboardingComplete
{
    private const EXEMPT_ROUTES = [
        'onboarding.index',
        'onboarding.complete',
        'logout',
        'admin.impersonate',
        'admin.impersonate.leave',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant || $tenant->hasCompletedOnboarding()) {
            return $next($request);
        }

        // Already heading to an exempt route — don't create a redirect loop
        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('onboarding.index');
    }
}
