<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'role'           => $user->role?->value,
                    'role_label'     => $user->role?->label(),
                    'is_super_admin' => $user->isSuperAdmin(),
                    'tenant'         => $user->tenant ? [
                        'id'                => $user->tenant->id,
                        'name'              => $user->tenant->name,
                        'plan'              => $user->tenant->plan?->value,
                        'plan_label'        => $user->tenant->plan?->label(),
                        'subscription_status' => $user->tenant->subscription_status?->value,
                        'is_trial'          => $user->tenant->subscription_status === TenantStatus::Trial,
                        'trial_days_remaining' => $user->tenant->trialDaysRemaining(),
                        'trial_ends_at'     => $user->tenant->trial_ends_at?->toDateString(),
                        'max_vendors'       => $user->tenant->plan?->maxVendors(),
                    ] : null,
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
