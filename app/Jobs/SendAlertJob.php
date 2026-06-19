<?php

namespace App\Jobs;

use App\Enums\AlertChannel;
use App\Enums\AlertStatus;
use App\Models\AlertLog;
use App\Services\Alerts\EmailAlertChannel;
use App\Services\Alerts\WhatsAppAlertChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly AlertLog $alertLog,
    ) {}

    public function handle(EmailAlertChannel $email, WhatsAppAlertChannel $whatsapp): void
    {
        $log = $this->alertLog;

        try {
            $messageId = match($log->channel) {
                AlertChannel::Email    => $email->send($log),
                AlertChannel::Whatsapp => $whatsapp->send($log),
                default                => throw new \RuntimeException("Unsupported channel: {$log->channel->value}"),
            };

            AlertLog::withoutGlobalScopes()->where('id', $log->id)->update([
                'status'              => AlertStatus::Sent->value,
                'provider_message_id' => $messageId,
                'sent_at'             => now(),
            ]);
        } catch (Throwable $e) {
            AlertLog::withoutGlobalScopes()->where('id', $log->id)->update([
                'status'        => AlertStatus::Failed->value,
                'failed_reason' => substr($e->getMessage(), 0, 500),
            ]);

            Log::warning('SendAlertJob failed', [
                'alert_log_id' => $log->id,
                'channel'      => $log->channel->value,
                'recipient'    => $log->recipient,
                'error'        => $e->getMessage(),
            ]);

            throw $e; // Allow queue retries
        }
    }
}
