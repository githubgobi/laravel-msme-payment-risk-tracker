<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;

/**
 * Enforces plan-based resource limits.
 *
 * Limits are defined on TenantPlan enum (maxVendors(), maxUsers()).
 * All queries bypass TenantScope (console/service context).
 */
final class PlanLimitService
{
    public function canAddVendor(Tenant $tenant): bool
    {
        $max = $tenant->plan->maxVendors();
        if ($max === PHP_INT_MAX) {
            return true;
        }

        return $this->currentVendorCount($tenant) < $max;
    }

    public function canAddUser(Tenant $tenant): bool
    {
        $max = $tenant->plan->maxUsers();
        if ($max === PHP_INT_MAX) {
            return true;
        }

        return $this->currentUserCount($tenant) < $max;
    }

    public function currentVendorCount(Tenant $tenant): int
    {
        return Vendor::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();
    }

    public function currentUserCount(Tenant $tenant): int
    {
        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();
    }

    public function vendorLimitMessage(Tenant $tenant): string
    {
        $max  = $tenant->plan->maxVendors();
        $used = $this->currentVendorCount($tenant);

        return "Vendor limit reached ({$used}/{$max}). Upgrade your plan to add more vendors.";
    }

    public function userLimitMessage(Tenant $tenant): string
    {
        $max  = $tenant->plan->maxUsers();
        $used = $this->currentUserCount($tenant);

        return "User limit reached ({$used}/{$max}). Upgrade your plan to add more team members.";
    }
}
