<?php

namespace App\Http\Controllers;

use App\Enums\AlertChannel;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Http\Requests\UpdateAlertSettingsRequest;
use App\Models\AlertLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'channel', 'type', 'search']);

        $query = AlertLog::with([
            'invoice:id,invoice_number,vendor_id,effective_deadline',
            'invoice.vendor:id,name',
        ])->latest();

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['channel'] ?? null) {
            $query->where('channel', $filters['channel']);
        }
        if ($filters['type'] ?? null) {
            $query->where('alert_type', $filters['type']);
        }
        if ($filters['search'] ?? null) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                  ->orWhereHas('invoice', fn ($qi) =>
                      $qi->where('invoice_number', 'like', "%{$search}%")
                  )
                  ->orWhereHas('invoice.vendor', fn ($qv) =>
                      $qv->where('name', 'like', "%{$search}%")
                  );
            });
        }

        $logs = $query->paginate(25)->withQueryString()
            ->through(fn ($log) => $this->formatLog($log));

        $thisMonth = now()->startOfMonth();

        $summary = [
            'total_this_month' => AlertLog::where('created_at', '>=', $thisMonth)->count(),
            'sent'             => AlertLog::where('status', AlertStatus::Sent->value)->where('created_at', '>=', $thisMonth)->count(),
            'failed'           => AlertLog::where('status', AlertStatus::Failed->value)->where('created_at', '>=', $thisMonth)->count(),
            'pending'          => AlertLog::where('status', AlertStatus::Pending->value)->count(),
        ];

        $tenant   = auth()->user()->tenant;
        $settings = $this->defaultSettings($tenant->settings['alerts'] ?? []);

        return Inertia::render('Alerts/Index', [
            'logs'       => $logs,
            'summary'    => $summary,
            'settings'   => $settings,
            'filters'    => $filters,
            'channels'   => collect(AlertChannel::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'alertTypes' => collect(AlertType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ])->values(),
            'statuses'   => collect(AlertStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
        ]);
    }

    public function updateSettings(UpdateAlertSettingsRequest $request): RedirectResponse
    {
        $tenant   = auth()->user()->tenant;
        $settings = $tenant->settings ?? [];
        $settings['alerts'] = $request->validated();
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Alert settings saved.');
    }

    private function formatLog(AlertLog $log): array
    {
        return [
            'id'                  => $log->id,
            'created_at'          => $log->created_at?->toIso8601String(),
            'alert_type'          => $log->alert_type->value,
            'alert_type_label'    => $log->alert_type->label(),
            'channel'             => $log->channel->value,
            'channel_label'       => $log->channel->label(),
            'recipient'           => $log->recipient,
            'status'              => $log->status->value,
            'status_label'        => $log->status->label(),
            'failed_reason'       => $log->failed_reason,
            'sent_at'             => $log->sent_at?->toIso8601String(),
            'provider_message_id' => $log->provider_message_id,
            'invoice' => $log->invoice ? [
                'id'             => $log->invoice->id,
                'invoice_number' => $log->invoice->invoice_number,
                'deadline'       => $log->invoice->effective_deadline?->format('d M Y'),
                'vendor_name'    => $log->invoice->vendor?->name,
            ] : null,
        ];
    }

    private function defaultSettings(array $saved): array
    {
        return array_merge([
            'email_enabled'    => true,
            'whatsapp_enabled' => false,
            'email_recipients' => [],
            'whatsapp_number'  => '',
            't10_enabled'      => true,
            't3_enabled'       => true,
            'overdue_enabled'  => true,
        ], $saved);
    }
}
