<?php

namespace App\Models;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'gstin',
        'pan',
        'business_type',
        'state',
        'city',
        'address',
        'phone',
        'email',
        'plan',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'rbi_bank_rate',
        'settings',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'plan'                  => TenantPlan::class,
            'subscription_status'   => TenantStatus::class,
            'trial_ends_at'         => 'datetime',
            'subscription_ends_at'  => 'datetime',
            'rbi_bank_rate'         => 'decimal:2',
            'settings'              => 'array',
            'is_active'             => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function alertLog(): HasMany
    {
        return $this->hasMany(AlertLog::class);
    }

    public function isAccessible(): bool
    {
        return $this->subscription_status->isAccessible();
    }

    public function effectiveInterestRate(): float
    {
        return (float) $this->rbi_bank_rate * 3;
    }
}
