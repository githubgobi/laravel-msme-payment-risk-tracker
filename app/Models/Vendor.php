<?php

namespace App\Models;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Traits\HasAuditColumns;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, HasTenant, HasAuditColumns;

    protected $fillable = [
        'tenant_id',
        'name',
        'aliases',
        'gstin',
        'pan',
        'udyam_number',
        'udyam_verified_at',
        'category',
        'verification_source',
        'state',
        'city',
        'address',
        'contact_person',
        'phone',
        'email',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'aliases'            => 'array',
            'category'           => VendorCategory::class,
            'verification_source'=> VendorVerificationSource::class,
            'udyam_verified_at'  => 'datetime',
            'is_active'          => 'boolean',
        ];
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function isSubjectTo43Bh(): bool
    {
        return $this->category->isSubjectTo43Bh();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMsme($query)
    {
        return $query->whereIn('category', [
            VendorCategory::Micro->value,
            VendorCategory::Small->value,
        ]);
    }
}
