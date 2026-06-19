<?php

namespace App\Services;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TenantRegistrationService
{
    public const TRIAL_DAYS = 14;

    /**
     * Register a new business account:
     * - Creates Tenant (trial, 14 days)
     * - Creates Owner User
     * - Returns the new User for immediate login
     *
     * Wrapped in a transaction — if user creation fails, the tenant is rolled back.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name'                => $data['business_name'],
                'email'               => $data['email'],
                'gstin'               => $data['gstin'] ?? null,
                'plan'                => TenantPlan::Starter->value,
                'subscription_status' => TenantStatus::Trial->value,
                'trial_ends_at'       => now()->addDays(self::TRIAL_DAYS),
                'rbi_bank_rate'       => 6.75,
                'is_active'           => true,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'], // hashed by cast
                'role'      => UserRole::Owner->value,
                'phone'     => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            return $user;
        });
    }
}
