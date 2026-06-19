<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\AlertDispatcherService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Dispatches 43B(h) deadline alerts for all active tenants.
 *
 * Schedule in routes/console.php or cron:
 *   php artisan msme:send-alerts
 *
 * Options:
 *   --tenant=ID   Process a single tenant only
 *   --dry-run     Show what would be dispatched without actually dispatching
 */
class SendMsmeAlerts extends Command
{
    protected $signature = 'msme:send-alerts
                            {--tenant= : Only process this tenant ID}
                            {--as-of=  : Override today\'s date (YYYY-MM-DD) for testing}
                            {--dry-run : Show qualifying invoices without dispatching}';

    protected $description = 'Dispatch 43B(h) deadline and overdue alerts to all active tenants';

    public function handle(AlertDispatcherService $dispatcher): int
    {
        $asOf     = $this->option('as-of')
            ? Carbon::parse($this->option('as-of'))->startOfDay()
            : Carbon::today();

        $dryRun   = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $query = Tenant::whereIn('subscription_status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->with('users')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            return self::SUCCESS;
        }

        $totalDispatched = $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing: <info>{$tenant->name}</info> (ID: {$tenant->id})");

            if ($dryRun) {
                $this->previewTenant($dispatcher, $tenant, $asOf);
                continue;
            }

            $result = $dispatcher->dispatchForTenant($tenant, $asOf);
            $totalDispatched += $result['dispatched'];
            $totalSkipped    += $result['skipped'];

            $this->line(
                "  → Dispatched: {$result['dispatched']}  Skipped (dedup): {$result['skipped']}"
            );
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info("Done. Total dispatched: {$totalDispatched}  Skipped: {$totalSkipped}");
        }

        return self::SUCCESS;
    }

    private function previewTenant(AlertDispatcherService $dispatcher, Tenant $tenant, Carbon $asOf): void
    {
        $config  = $tenant->settings['alerts'] ?? [];
        $types   = $dispatcher->resolveTypes($config);
        $channels = $dispatcher->resolveChannels($tenant, $config);

        if (empty($channels)) {
            $this->line('  → No channels configured — would skip.');
            return;
        }

        foreach ($types as $type) {
            $invoices = $dispatcher->qualifyingInvoices($tenant, $type, $asOf);
            $this->line(
                "  → [{$type->label()}] {$invoices->count()} qualifying invoice(s)"
            );
            foreach ($invoices as $inv) {
                $this->line(
                    "     • {$inv->invoice_number}  {$inv->vendor?->name}  " .
                    "Deadline: {$inv->effective_deadline->format('d M Y')}"
                );
            }
        }
    }
}
