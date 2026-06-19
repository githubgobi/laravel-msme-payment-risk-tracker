<?php

namespace App\Services\Alerts;

use App\Models\AlertLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp delivery via AiSensy Business API.
 *
 * Requirements:
 *   - AISENSY_API_KEY in .env
 *   - A pre-approved template named msme_43bh_alert with 5 parameters:
 *       {{1}} vendor name
 *       {{2}} balance amount (₹)
 *       {{3}} deadline date
 *       {{4}} days remaining / overdue text
 *       {{5}} disallowance amount (₹)
 */
final class WhatsAppAlertChannel implements AlertChannelInterface
{
    private const API_URL     = 'https://backend.aisensy.com/campaign/t1/api/v2';
    private const TIMEOUT_SEC = 15;

    public function send(AlertLog $log): ?string
    {
        $apiKey = config('services.aisensy.key');

        if (! $apiKey) {
            throw new RuntimeException('AiSensy API key (AISENSY_API_KEY) is not configured.');
        }

        $invoice = $log->invoice()->withoutGlobalScopes()
            ->with(['vendor:id,name'])
            ->firstOrFail();

        $balance     = (float) $invoice->amount - (float) $invoice->paid_amount;
        $today       = now()->startOfDay();
        $deadline    = $invoice->effective_deadline;
        $diffDays    = (int) $today->diffInDays($deadline, false);
        $daysText    = $diffDays >= 0
            ? "{$diffDays} day" . ($diffDays !== 1 ? 's' : '') . ' remaining'
            : abs($diffDays) . ' day' . (abs($diffDays) !== 1 ? 's' : '') . ' overdue';

        $response = Http::timeout(self::TIMEOUT_SEC)
            ->post(self::API_URL, [
                'apiKey'         => $apiKey,
                'campaignName'   => config('services.aisensy.campaign_name', 'msme_43bh_alert'),
                'destination'    => $log->recipient,
                'userName'       => config('services.aisensy.user_name', 'MSME Tracker'),
                'templateParams' => [
                    $invoice->vendor?->name ?? 'Unknown Vendor',
                    '₹' . number_format($balance, 2),
                    $deadline->format('d M Y'),
                    $daysText,
                    '₹' . number_format((float) $invoice->disallowance_amount, 2),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "AiSensy API returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json('messageId');
    }
}
