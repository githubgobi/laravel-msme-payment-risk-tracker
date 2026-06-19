<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prune audit_logs records older than the given number of years.
 *
 * Section 43B(h) compliance requires an 8-year audit trail.
 * Default cutoff is 10 years to provide a 2-year safety margin.
 *
 * Scheduled: Sundays at 01:00 UTC (routes/console.php)
 */
class PruneAuditLog extends Command
{
    protected $signature   = 'audit:prune {--years=10 : Delete records older than this many years}';
    protected $description = 'Delete audit log entries older than the specified number of years';

    public function handle(): int
    {
        $years  = (int) $this->option('years');
        $cutoff = now()->subYears($years)->toDateTimeString();

        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} audit log record(s) older than {$years} years (before {$cutoff}).");

        return self::SUCCESS;
    }
}
