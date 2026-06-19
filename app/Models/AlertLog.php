<?php

namespace App\Models;

use App\Enums\AlertChannel;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertLog extends Model
{
    use HasFactory, HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'channel',
        'recipient',
        'alert_type',
        'status',
        'payload',
        'provider_message_id',
        'failed_reason',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'channel'      => AlertChannel::class,
            'alert_type'   => AlertType::class,
            'status'       => AlertStatus::class,
            'payload'      => 'array',
            'sent_at'      => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }
}
