<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding checklist page.
     *
     * Only visible to tenants that have NOT yet completed onboarding.
     * Super-admins and completed tenants are never redirected here.
     */
    public function index(Request $request): Response
    {
        $user   = $request->user();
        $tenant = $user->tenant;

        $steps = $this->buildChecklist($tenant);

        return Inertia::render('Onboarding/Index', [
            'tenantName'    => $tenant->name,
            'steps'         => $steps,
            'allComplete'   => collect($steps)->every(fn ($s) => $s['done']),
        ]);
    }

    /**
     * Mark onboarding as complete for the tenant.
     * Called from the onboarding page once all steps are checked.
     */
    public function complete(Request $request): RedirectResponse
    {
        $request->user()->tenant->update([
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome! Your account is all set up.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function buildChecklist($tenant): array
    {
        $hasVendors  = $tenant->vendors()->count() > 0;
        $hasInvoices = $tenant->purchaseInvoices()->count() > 0;
        $hasAlerts   = (bool) ($tenant->settings['alert_enabled'] ?? false);
        $hasTeam     = $tenant->users()->count() > 1;

        return [
            [
                'key'         => 'profile',
                'title'       => 'Complete your company profile',
                'description' => 'Add your GSTIN, state, and contact details.',
                'done'        => ! empty($tenant->gstin) && ! empty($tenant->state),
                'href'        => route('settings.index'),
            ],
            [
                'key'         => 'vendors',
                'title'       => 'Add your MSME vendors',
                'description' => 'Import via Excel or add vendors manually.',
                'done'        => $hasVendors,
                'href'        => route('vendors.index'),
            ],
            [
                'key'         => 'invoices',
                'title'       => 'Import your first invoice batch',
                'description' => 'Upload a purchase ledger CSV or Tally export.',
                'done'        => $hasInvoices,
                'href'        => route('import.index'),
            ],
            [
                'key'         => 'alerts',
                'title'       => 'Configure payment alerts',
                'description' => 'Turn on email or WhatsApp alerts for T-10 and T-3 deadlines.',
                'done'        => $hasAlerts,
                'href'        => route('alerts.index'),
            ],
            [
                'key'         => 'team',
                'title'       => 'Invite a team member',
                'description' => 'Add your CA, accountant, or finance manager.',
                'done'        => $hasTeam,
                'href'        => route('settings.index'),
            ],
        ];
    }
}
