<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceType;
use App\Enums\VendorCategory;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;

/**
 * Indexes a tenant's classified vendor records into the knowledge base so that
 * the RAG system can retrieve real examples when classifying new vendors.
 *
 * Each vendor becomes one document with one chunk formatted as:
 *   "Vendor: {name} | Category: {category} | State: {state} | GSTIN: {gstin}"
 *
 * Only classified (non-Unclassified) vendors are indexed — Unclassified records
 * carry no useful signal for RAG retrieval.
 */
final class VendorIngester
{
    public function __construct(
        private readonly KnowledgeRepository $repository,
    ) {}

    /**
     * Index or re-index all classified vendors for a tenant.
     * Returns ['indexed' => int, 'skipped' => int].
     */
    public function ingestAll(int $tenantId, ?int $userId = null): array
    {
        $vendors = Vendor::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotIn('category', [VendorCategory::Unclassified->value])
            ->get();

        $indexed = 0;
        $skipped = 0;

        foreach ($vendors as $vendor) {
            try {
                $this->ingestVendor($vendor, $tenantId, $userId);
                $indexed++;
            } catch (\Throwable $e) {
                Log::warning('VendorIngester: failed to index vendor', [
                    'vendor_id' => $vendor->id,
                    'error'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        Log::info('VendorIngester: ingestAll complete', [
            'tenant_id' => $tenantId,
            'indexed'   => $indexed,
            'skipped'   => $skipped,
        ]);

        return ['indexed' => $indexed, 'skipped' => $skipped];
    }

    /**
     * Index or re-index a single vendor. Safe to call repeatedly — overwrites
     * the previous version rather than creating duplicates.
     */
    public function ingestVendor(Vendor $vendor, int $tenantId, ?int $userId = null): void
    {
        $content = $this->buildVendorContent($vendor);

        $this->repository->reindexDocument(
            tenantId:   $tenantId,
            title:      "Vendor: {$vendor->name}",
            content:    $content,
            sourceType: KnowledgeSourceType::Vendor,
            sourceId:   $vendor->id,
            createdBy:  $userId,
        );
    }

    private function buildVendorContent(Vendor $vendor): string
    {
        $parts = [
            "Vendor: {$vendor->name}",
            "Category: {$vendor->category->label()}",
        ];

        if ($vendor->gstin) {
            $parts[] = "GSTIN: {$vendor->gstin}";
        }

        if ($vendor->state) {
            $parts[] = "State: {$vendor->state}";
        }

        if ($vendor->udyam_number) {
            $parts[] = "Udyam: {$vendor->udyam_number}";
        }

        return implode(' | ', $parts);
    }
}
