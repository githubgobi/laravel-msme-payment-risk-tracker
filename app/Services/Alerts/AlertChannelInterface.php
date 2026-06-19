<?php

namespace App\Services\Alerts;

use App\Models\AlertLog;

interface AlertChannelInterface
{
    /**
     * Send the alert and return a provider message ID (or null).
     *
     * @throws \Throwable on unrecoverable send failure
     */
    public function send(AlertLog $log): ?string;
}
