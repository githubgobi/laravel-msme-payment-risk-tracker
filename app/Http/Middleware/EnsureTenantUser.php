<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks super-admins (tenant_id = null) from accessing tenant-scoped routes
 * unless they are actively impersonating a tenant owner.
 *
 * Without this guard every tenant controller crashes on $user->tenant->id
 * because super-admins have no tenant record.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuperAdmin()) {
            $isImpersonating = $request->session()->has('impersonating_admin_id');

            if (! $isImpersonating) {
                return redirect('/admin');
            }
        }

        return $next($request);
    }
}
