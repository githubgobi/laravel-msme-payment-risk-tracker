<?php

namespace App\Services\Import;

use App\DTOs\LlmMatchResult;
use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Vendor;
use App\Services\Llm\VendorFuzzyMatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finds an existing vendor or creates a new one.
 *
 * Match priority (within tenant):
 *   1. GSTIN (exact, case-insensitive)
 *   2. Udyam number (exact, case-insensitive)
 *   3. Vendor name (exact, trimmed, case-insensitive)
 *   3.5 LLM fuzzy match (when LLM_ENABLED=true and exact matches all fail)
 *   4. Create new vendor with category = unclassified
 *
 * Maintains an in-memory cache for the duration of a single import
 * to avoid repeated DB lookups for the same vendor in the same file.
 */
final class VendorMatcher
{
    /** @var array<string, Vendor> */
    private array $cache = [];

    public function __construct(
        private readonly ?VendorFuzzyMatcher $fuzzyMatcher = null,
        private readonly bool                $llmEnabled   = false,
    ) {}

    /**
     * @throws \RuntimeException when vendor not found and $canCreate is false (plan limit reached)
     */
    public function findOrCreate(
        string   $vendorName,
        int      $tenantId,
        ?int     $createdBy,
        string   $gstin       = '',
        string   $udyamNumber = '',
        bool     $canCreate   = true,
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

        // 3.5 LLM fuzzy match — only when enabled and at least 1 vendor exists in tenant
        if ($this->llmEnabled && $this->fuzzyMatcher !== null) {
            $matched = $this->llmFuzzyMatch($vendorName, $tenantId);
            if ($matched) {
                $this->cache[$cacheKey] = $matched;
                return $matched;
            }
        }

        // 4. Create new vendor — category unclassified until classified
        if (! $canCreate) {
            throw new \RuntimeException(
                "Vendor '{$vendorName}' not found and your plan's vendor limit has been reached."
            );
        }

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

        $this->cache["name:{$tenantId}:" . Str::lower($vendorName)] = $vendor;
        if ($gstin !== '') {
            $this->cache["gstin:{$tenantId}:{$gstin}"] = $vendor;
        }
        if ($udyamNumber !== '') {
            $this->cache["udyam:{$tenantId}:{$udyamNumber}"] = $vendor;
        }

        return $vendor;
    }

    public function resetCache(): void
    {
        $this->cache = [];
    }

    private function llmFuzzyMatch(string $vendorName, int $tenantId): ?Vendor
    {
        $candidates = Vendor::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select(['id', 'name', 'gstin', 'state'])
            ->limit(20)
            ->get()
            ->map(fn ($v) => [
                'id'    => $v->id,
                'name'  => $v->name,
                'gstin' => $v->gstin,
                'state' => $v->state,
            ])
            ->toArray();

        if (empty($candidates)) {
            return null;
        }

        /** @var LlmMatchResult|null $result */
        $result = $this->fuzzyMatcher->findBestMatch($vendorName, $candidates);

        if ($result === null) {
            return null;
        }

        $vendor = Vendor::withoutGlobalScopes()
            ->where('id', $result->vendorId)
            ->whereNull('deleted_at')
            ->first();

        if ($vendor) {
            Log::info('VendorMatcher: LLM fuzzy match resolved', [
                'imported_name' => $vendorName,
                'matched_to'    => $vendor->name,
                'confidence'    => $result->confidence,
                'reasoning'     => $result->reasoning,
            ]);
        }

        return $vendor;
    }
}
