<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Models\Traits\HasAuditColumns;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, HasTenant, HasAuditColumns;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payment_date',
        'amount',
        'payment_mode',
        'reference_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount'       => 'decimal:2',
            'payment_mode' => PaymentMode::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function isPaidBeforeDeadline(): bool
    {
        return $this->payment_date->lte($this->invoice->effective_deadline);
    }
}
