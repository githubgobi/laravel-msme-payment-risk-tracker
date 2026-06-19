<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\InvoiceRiskRecomputer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecomputeMsmeRisk extends Command
{
    protected $signature = 'msme:recompute-risk
                            {--tenant= : Recompute only for this tenant ID}
                            {--as-of=  : Compute as of this date (Y-m-d). Defaults to today.}';

    protected $description = 'Recompute 43B(h) disallowance and interest for all at-risk invoices';

    public function handle(InvoiceRiskRecomputer $recomputer): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse($this->option('as-of'))
            : Carbon::today();

        $this->info("Recomputing MSME risk as of {$asOf->toDateString()}...");

        $query = Tenant::where('is_active', true);

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $this->line("  → Tenant [{$tenant->id}] {$tenant->name}");
            $count = $recomputer->recomputeForTenant($tenant, $asOf);
            $this->line("    Updated {$count} invoices.");
            $totalUpdated += $count;
        }

        $this->newLine();
        $this->info("Done. Total invoices recomputed: {$totalUpdated}");

        return self::SUCCESS;
    }
}
