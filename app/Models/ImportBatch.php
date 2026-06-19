<?php

namespace App\Models;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'source',
        'original_filename',
        'stored_path',
        'total_rows',
        'processed_rows',
        'skipped_rows',
        'failed_rows',
        'status',
        'error_log',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source'       => ImportSource::class,
            'status'       => ImportStatus::class,
            'error_log'    => 'array',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function successRate(): float
    {
        if ($this->total_rows === 0) {
            return 0.0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }
}
