<?php

namespace App\Filament\Widgets;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $activeTenants  = Tenant::withoutGlobalScopes()->where('subscription_status', TenantStatus::Active)->count();
        $trialTenants   = Tenant::withoutGlobalScopes()->where('subscription_status', TenantStatus::Trial)->count();
        $totalTenants   = Tenant::withoutGlobalScopes()->count();
        $newThisMonth   = Tenant::withoutGlobalScopes()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        // MRR calculation: sum of plan prices for active subscriptions
        $mrr = Tenant::withoutGlobalScopes()
            ->where('subscription_status', TenantStatus::Active)
            ->get()
            ->sum(fn (Tenant $t) => $t->plan?->monthlyPriceInr() ?? 0);

        $churnedThisMonth = Tenant::withoutGlobalScopes()
            ->where('subscription_status', TenantStatus::Inactive)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            Stat::make('Monthly Recurring Revenue', '₹' . number_format($mrr, 0, '.', ','))
                ->description("from {$activeTenants} active tenant(s)")
                ->color('success'),

            Stat::make('Active Tenants', $activeTenants)
                ->description("{$trialTenants} on trial")
                ->color('primary'),

            Stat::make('Total Tenants', $totalTenants)
                ->description("{$newThisMonth} joined this month")
                ->color('info'),

            Stat::make('Churned This Month', $churnedThisMonth)
                ->color($churnedThisMonth > 0 ? 'danger' : 'gray'),
        ];
    }
}
