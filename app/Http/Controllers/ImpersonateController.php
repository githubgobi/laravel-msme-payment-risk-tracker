<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Allows super-admins to impersonate any tenant's owner.
 *
 * Session key "impersonating_admin_id" stores the original admin's user ID
 * so /impersonate/leave can restore it.  The original auth state is preserved
 * using the guard's user-switch mechanism (login as owner) and the standard
 * session driver (file).
 */
class ImpersonateController extends Controller
{
    private const SESSION_KEY = 'impersonating_admin_id';

    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($request->session()->has(self::SESSION_KEY), 403, 'Already impersonating.');

        $owner = $tenant->users()->where('role', 'owner')->firstOrFail();

        $request->session()->put(self::SESSION_KEY, $request->user()->id);

        Auth::login($owner);

        return redirect()->route('dashboard')
            ->with('info', "Impersonating {$tenant->name}. Click 'Leave impersonation' to return.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);

        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::findOrFail($adminId);
        Auth::login($admin);

        return redirect('/admin');
    }
}
