<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTeamUserRequest;
use App\Http\Requests\Settings\UpdateTeamUserRequest;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;

class TeamController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimits,
    ) {}

    public function store(StoreTeamUserRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if (! $this->planLimits->canAddUser($tenant)) {
            return back()->withErrors([
                'email' => $this->planLimits->userLimitMessage($tenant),
            ]);
        }

        User::create([
            'tenant_id' => $tenant->id,
            'name'      => $request->validated('name'),
            'email'     => $request->validated('email'),
            'password'  => $request->validated('password'),
            'role'      => $request->validated('role'),
            'phone'     => $request->validated('phone'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Team member added.');
    }

    public function update(UpdateTeamUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeTeamAction($request->user(), $user);

        $user->update($request->validated());

        return back()->with('success', 'Team member updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $actingUser = auth()->user();
        $this->authorizeTeamAction($actingUser, $user);

        if ($actingUser->id === $user->id) {
            return back()->withErrors(['general' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('success', 'Team member deactivated.');
    }

    private function authorizeTeamAction(User $actor, User $target): void
    {
        // Must be same tenant
        if ($actor->tenant_id !== $target->tenant_id) {
            abort(403);
        }
        // Must have management rights
        if (! $actor->canManageUsers()) {
            abort(403);
        }
    }
}
