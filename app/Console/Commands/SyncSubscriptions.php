<?php

namespace App\Console\Commands;

use App\Services\TenantSubscriptionService;
use Illuminate\Console\Command;

/**
 * Reconcile all tenant subscriptions with Razorpay.
 *
 * Scheduled every 6 hours (routes/console.php) as a safety net
 * in case webhooks are missed or arrive out of order.
 * The primary source of truth is still the webhook events.
 */
class SyncSubscriptions extends Command
{
    protected $signature   = 'subscriptions:sync';
    protected $description = 'Sync tenant subscription statuses from Razorpay';

    public function __construct(private readonly TenantSubscriptionService $subscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing tenant subscriptions from Razorpay...');

        try {
            $synced = $this->subscriptions->syncAll();
            $this->info("Synced {$synced} subscription(s).");
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
