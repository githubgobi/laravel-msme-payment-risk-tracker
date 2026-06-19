<?php

namespace App\Services\Import;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Vendor;
use Illuminate\Support\Str;

/**
 * Finds an existing vendor or creates a new one.
 *
 * Match priority (within tenant):
 *   1. GSTIN (exact, case-insensitive)
 *   2. Udyam number (exact, case-insensitive)
 *   3. Vendor name (exact, trimmed, case-insensitive)
 *   4. Create new vendor with category = unclassified
 *
 * Maintains an in-memory cache for the duration of a single import
 * to avoid repeated DB lookups for the same vendor in the same file.
 */
final class VendorMatcher
{
    /** @var array<string, Vendor> */
    private array $cache = [];

    /**
     * Find or create a vendor for the given import row data.
     *
     * @param  int       $tenantId
     * @param  int|null  $createdBy  Batch creator user ID
     */
    public function findOrCreate(
        string   $vendorName,
        int      $tenantId,
        ?int     $createdBy,
        string   $gstin       = '',
        string   $udyamNumber = '',
    ): Vendor {
        $gstin       = strtoupper(trim($gstin));
        $udyamNumber = strtoupper(trim($udyamNumber));
        $vendorName  = trim($vendorName);

        // 1. Match by GSTIN
        if ($gstin !== '') {
            $cacheKey = "gstin:{$tenantId}:{$gstin}";
            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            $vendor = Vendor::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gstin', $gstin)
                ->whereNull('deleted_at')
                ->first();

            if ($vendor) {
                $this->cache[$cacheKey] = $vendor;
                return $vendor;
            }
        }

        // 2. Match by Udyam number
        if ($udyamNumber !== '') {
            $cacheKey = "udyam:{$tenantId}:{$udyamNumber}";
            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            $vendor = Vendor::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('udyam_number', $udyamNumber)
                ->whereNull('deleted_at')
                ->first();

            if ($vendor) {
                $this->cache[$cacheKey] = $vendor;
                return $vendor;
            }
        }

        // 3. Match by exact vendor name (case-insensitive)
        $cacheKey = "name:{$tenantId}:" . Str::lower($vendorName);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $vendor = Vendor::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($vendorName)])
            ->whereNull('deleted_at')
            ->first();

        if ($vendor) {
            $this->cache[$cacheKey] = $vendor;
            return $vendor;
        }

        // 4. Create new vendor — category unclassified until Phase 4 classifies it
        $vendor = Vendor::withoutGlobalScopes()->create([
            'tenant_id'           => $tenantId,
            'name'                => $vendorName,
            'gstin'               => $gstin ?: null,
            'udyam_number'        => $udyamNumber ?: null,
            'category'            => VendorCategory::Unclassified,
            'verification_source' => VendorVerificationSource::Manual,
            'is_active'           => true,
            'created_by'          => $createdBy,
            'updated_by'          => $createdBy,
        ]);

        // Cache under all available keys
        $this->cache["name:{$tenantId}:" . Str::lower($vendorName)] = $vendor;
        if ($gstin !== '') {
            $this->cache["gstin:{$tenantId}:{$gstin}"] = $vendor;
        }
        if ($udyamNumber !== '') {
            $this->cache["udyam:{$tenantId}:{$udyamNumber}"] = $vendor;
        }

        return $vendor;
    }

    /** Reset the in-memory cache (call between separate imports). */
    public function resetCache(): void
    {
        $this->cache = [];
    }
}
