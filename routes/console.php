<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Production Scheduler
|--------------------------------------------------------------------------
|
| Requires: * * * * * cd /var/www/msme-tracker && php artisan schedule:run >> /dev/null 2>&1
| in the server's crontab (or Supervisor's scheduler process).
|
*/

// Nightly risk recompute — runs at 00:05 IST (18:35 UTC) for all tenants
// Recalculates disallowance_amount, interest_amount, and invoice status
Schedule::command('msme:recompute-risk')
    ->dailyAt('18:35')
    ->timezone('UTC')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-recompute.log'));

// Daily alert dispatch — runs at 08:00 IST (02:30 UTC)
// Sends T-10, T-3, overdue, and year-end summary alerts via email + WhatsApp
Schedule::command('msme:send-alerts')
    ->dailyAt('02:30')
    ->timezone('UTC')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-alerts.log'));

// Sync Razorpay subscription statuses — runs every 4 hours
// Reconciles subscription_status, subscription_ends_at, and grace periods
Schedule::command('subscriptions:sync')
    ->everySixHours()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-subscriptions.log'));

// AI vendor classification — runs nightly at 23:30 UTC (05:00 IST)
// Auto-classifies Unclassified vendors via LLM and re-indexes the RAG knowledge base
Schedule::command('ai:classify-vendors')
    ->dailyAt('23:30')
    ->timezone('UTC')
    ->withoutOverlapping(90)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ai-classify.log'));

// Prune old audit log entries — runs Sunday at 01:00 UTC
// Section 43B(h) compliance requires 8-year audit trail retention
// Only deletes records older than 10 years as a safety margin
Schedule::command('audit:prune --years=10')
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->timezone('UTC')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/scheduler-prune.log'));
