<?php

namespace App\Services;

use App\Enums\AlertChannel;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\InvoiceStatus;
use App\Jobs\SendAlertJob;
use App\Models\AlertLog;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds qualifying invoices for each alert type, deduplicates against today's
 * alert_log, creates AlertLog records, and dispatches SendAlertJob for each.
 *
 * Runs in console context — all DB queries use withoutGlobalScopes() and
 * explicit tenant_id filters since auth() is not set.
 */
final class AlertDispatcherService
{
    /** Invoice deadline window for T10: 8–10 days out */
    private const T10_MIN_DAYS = 8;
    private const T10_MAX_DAYS = 10;

    /** Invoice deadline window for T3: 1–3 days out */
    private const T3_MIN_DAYS = 1;
    private const T3_MAX_DAYS = 3;

    /**
     * Dispatch alerts for a single tenant.
     * Returns counts: dispatched, skipped (dedup), channels_empty.
     */
    public function dispatchForTenant(Tenant $tenant, ?Carbon $asOf = null): array
    {
        $asOf    = $asOf ?? Carbon::today();
        $config  = $tenant->settings['alerts'] ?? [];
        $channels = $this->resolveChannels($tenant, $config);

        if (empty($channels)) {
            return ['dispatched' => 0, 'skipped' => 0, 'reason' => 'no_channels'];
        }

        $dispatched = $skipped = 0;
        $types      = $this->resolveTypes($config);

        foreach ($types as $type) {
            $invoices = $this->qualifyingInvoices($tenant, $type, $asOf);

            foreach ($invoices as $invoice) {
                foreach ($channels as [$channel, $recipient]) {
                    if ($this->alreadySentToday($tenant, $invoice->id, $type, $channel, $asOf)) {
                        $skipped++;
                        continue;
                    }

                    $log = AlertLog::withoutGlobalScopes()->create([
                        'tenant_id'  => $tenant->id,
                        'invoice_id' => $invoice->id,
                        'channel'    => $channel->value,
                        'recipient'  => $recipient,
                        'alert_type' => $type->value,
                        'status'     => AlertStatus::Pending->value,
                        'payload'    => [
                            'invoice_number' => $invoice->invoice_number,
                            'vendor_name'    => $invoice->vendor?->name,
                            'balance'        => (float) $invoice->amount - (float) $invoice->paid_amount,
                            'deadline'       => $invoice->effective_deadline->toDateString(),
                        ],
                    ]);

                    SendAlertJob::dispatch($log);
                    $dispatched++;
                }
            }
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    /**
     * Returns the list of [AlertChannel, recipient] pairs based on settings.
     */
    public function resolveChannels(Tenant $tenant, array $config): array
    {
        $channels = [];

        // Email channel
        if ($config['email_enabled'] ?? true) {
            $recipients = $config['email_recipients'] ?? [];

            if (empty($recipients)) {
                // Fallback: use all active users' emails
                $recipients = $tenant->users()
                    ->where('is_active', true)
                    ->pluck('email')
                    ->filter()
                    ->all();
            }

            foreach ($recipients as $email) {
                $channels[] = [AlertChannel::Email, $email];
            }
        }

        // WhatsApp channel
        if (($config['whatsapp_enabled'] ?? false) && ! empty($config['whatsapp_number'])) {
            $channels[] = [AlertChannel::Whatsapp, $config['whatsapp_number']];
        }

        return $channels;
    }

    /**
     * Returns enabled AlertTypes based on settings (all enabled by default).
     *
     * @return AlertType[]
     */
    public function resolveTypes(array $config): array
    {
        $types = [];

        if ($config['t10_enabled'] ?? true) {
            $types[] = AlertType::T10Warning;
        }
        if ($config['t3_enabled'] ?? true) {
            $types[] = AlertType::T3Urgent;
        }
        if ($config['overdue_enabled'] ?? true) {
            $types[] = AlertType::Overdue;
        }

        return $types;
    }

    /**
     * Returns invoices qualifying for the given alert type on $asOf date.
     */
    public function qualifyingInvoices(Tenant $tenant, AlertType $type, Carbon $asOf): Collection
    {
        $base = PurchaseInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->with(['vendor:id,name']);

        return match($type) {
            AlertType::T10Warning => (clone $base)
                ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
                ->whereDate('effective_deadline', '>=', $asOf->copy()->addDays(self::T10_MIN_DAYS))
                ->whereDate('effective_deadline', '<=', $asOf->copy()->addDays(self::T10_MAX_DAYS))
                ->get(),

            AlertType::T3Urgent => (clone $base)
                ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
                ->whereDate('effective_deadline', '>=', $asOf->copy()->addDays(self::T3_MIN_DAYS))
                ->whereDate('effective_deadline', '<=', $asOf->copy()->addDays(self::T3_MAX_DAYS))
                ->get(),

            AlertType::Overdue => (clone $base)
                ->where('status', InvoiceStatus::Overdue->value)
                ->whereDate('effective_deadline', '<', $asOf)
                ->get(),

            AlertType::YearEndSummary => collect(), // manual-only
        };
    }

    private function alreadySentToday(
        Tenant      $tenant,
        int         $invoiceId,
        AlertType   $type,
        AlertChannel $channel,
        Carbon      $asOf,
    ): bool {
        return AlertLog::withoutGlobalScopes()
            ->where('tenant_id',  $tenant->id)
            ->where('invoice_id', $invoiceId)
            ->where('alert_type', $type->value)
            ->where('channel',    $channel->value)
            ->whereDate('created_at', $asOf)
            ->exists();
    }
}
