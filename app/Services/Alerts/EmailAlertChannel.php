<?php

namespace App\Services\Alerts;

use App\Mail\InvoiceAlertMail;
use App\Models\AlertLog;
use Illuminate\Support\Facades\Mail;

final class EmailAlertChannel implements AlertChannelInterface
{
    public function send(AlertLog $log): ?string
    {
        $invoice = $log->invoice()->withoutGlobalScopes()
            ->with(['vendor:id,name'])
            ->firstOrFail();

        Mail::to($log->recipient)
            ->send(new InvoiceAlertMail($invoice, $log->alert_type));

        return null; // Email has no provider message ID
    }
}
