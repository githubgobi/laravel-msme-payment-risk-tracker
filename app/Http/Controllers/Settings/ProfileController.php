<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimits,
    ) {}

    public function index(): Response
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        return Inertia::render('Settings/Index', [
            'activeTab' => 'profile',
            'profile'   => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'gstin'         => $tenant->gstin,
                'pan'           => $tenant->pan,
                'business_type' => $tenant->business_type,
                'state'         => $tenant->state,
                'city'          => $tenant->city,
                'address'       => $tenant->address,
                'phone'         => $tenant->phone,
                'email'         => $tenant->email,
                'rbi_bank_rate' => (float) $tenant->rbi_bank_rate,
            ],
            'billing'   => $this->billingData($tenant),
            'team'      => $this->teamData($tenant),
            'limits'    => [
                'vendors_used'  => $this->planLimits->currentVendorCount($tenant),
                'vendors_max'   => $tenant->plan->maxVendors() === PHP_INT_MAX ? null : $tenant->plan->maxVendors(),
                'users_used'    => $this->planLimits->currentUserCount($tenant),
                'users_max'     => $tenant->plan->maxUsers() === PHP_INT_MAX ? null : $tenant->plan->maxUsers(),
            ],
            'canManage' => $user->canManageUsers(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->tenant->update($request->validated());

        return back()->with('success', 'Business profile updated.');
    }

    private function billingData($tenant): array
    {
        return [
            'plan'                  => $tenant->plan?->value,
            'plan_label'            => $tenant->plan?->label(),
            'plan_price'            => $tenant->plan?->monthlyPriceInr(),
            'subscription_status'  => $tenant->subscription_status?->value,
            'status_label'          => $tenant->subscription_status?->label(),
            'is_trial'              => $tenant->subscription_status?->value === 'trial',
            'trial_ends_at'         => $tenant->trial_ends_at?->toDateString(),
            'trial_days_remaining'  => $tenant->trialDaysRemaining(),
            'subscription_ends_at'  => $tenant->subscription_ends_at?->toDateString(),
        ];
    }

    private function teamData($tenant): array
    {
        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
                'role'      => $u->role?->value,
                'role_label' => $u->role?->label(),
                'phone'     => $u->phone,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->diffForHumans(),
            ]);

        return [
            'users' => $users,
            'roles' => collect(\App\Enums\UserRole::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ])->values(),
        ];
    }
}
