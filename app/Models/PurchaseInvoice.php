<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use App\Models\Traits\HasAuditColumns;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes, HasTenant, HasAuditColumns;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'import_batch_id',
        'invoice_number',
        'invoice_date',
        'amount',
        'paid_amount',
        'currency',
        'agreement_exists',
        'agreement_date',
        'effective_deadline',
        'vendor_category_snapshot',
        'financial_year',
        'disallowance_amount',
        'interest_amount',
        'last_computed_at',
        'status',
        'narration',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'             => 'date',
            'agreement_date'           => 'date',
            'effective_deadline'       => 'date',
            'last_computed_at'         => 'datetime',
            'agreement_exists'         => 'boolean',
            'amount'                   => 'decimal:2',
            'paid_amount'              => 'decimal:2',
            'balance'                  => 'decimal:2',
            'disallowance_amount'      => 'decimal:2',
            'interest_amount'          => 'decimal:2',
            'status'                   => InvoiceStatus::class,
            'vendor_category_snapshot' => VendorCategory::class,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function alertLog(): HasMany
    {
        return $this->hasMany(AlertLog::class, 'invoice_id');
    }

    public function scopeAtRisk($query)
    {
        return $query->whereIn('status', [
            InvoiceStatus::Pending->value,
            InvoiceStatus::Partial->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', InvoiceStatus::Overdue->value);
    }

    public function scopeDueWithinDays($query, int $days)
    {
        return $query->atRisk()
                     ->whereDate('effective_deadline', '<=', now()->addDays($days))
                     ->whereDate('effective_deadline', '>=', now());
    }

    public function scopeForFinancialYear($query, string $year)
    {
        return $query->where('financial_year', $year);
    }

    public function isSubjectTo43Bh(): bool
    {
        return $this->vendor_category_snapshot->isSubjectTo43Bh();
    }

    public function deadlinePaymentDays(): int
    {
        return $this->agreement_exists ? 45 : 15;
    }
}
