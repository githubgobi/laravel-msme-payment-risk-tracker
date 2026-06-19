<?php

namespace App\Services;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Jobs\PropagateVendorClassification;
use App\Models\Vendor;
use Carbon\Carbon;

/**
 * Classifies a vendor and schedules propagation to its active invoices.
 *
 * Propagation (updating vendor_category_snapshot on invoices + risk recompute)
 * is dispatched as an async job so the HTTP response is not blocked.
 */
final class VendorClassificationService
{
    /**
     * Classify a single vendor.
     *
     * @param  Vendor                   $vendor
     * @param  VendorCategory           $category
     * @param  string|null              $udyamNumber    Pass null to leave unchanged
     * @param  VendorVerificationSource $source
     * @param  Carbon|null              $verifiedAt     null = not API-verified
     */
    public function classify(
        Vendor                   $vendor,
        VendorCategory           $category,
        ?string                  $udyamNumber = null,
        VendorVerificationSource $source      = VendorVerificationSource::Manual,
        ?Carbon                  $verifiedAt  = null,
    ): void {
        $categoryChanged = $vendor->category !== $category;

        $vendor->withoutGlobalScopes()->where('id', $vendor->id)->update(array_filter([
            'category'            => $category->value,
            'udyam_number'        => $udyamNumber ?? $vendor->udyam_number,
            'verification_source' => $source->value,
            'udyam_verified_at'   => $verifiedAt?->toDateTimeString(),
        ], fn ($v) => $v !== null));

        $vendor->refresh();

        if ($categoryChanged) {
            PropagateVendorClassification::dispatch($vendor);
        }
    }

    /**
     * Classify multiple vendors to the same category in bulk.
     * Dispatches one propagation job per vendor whose category actually changed.
     *
     * @param  Vendor[]|iterable  $vendors
     */
    public function bulkClassify(
        iterable       $vendors,
        VendorCategory $category,
    ): int {
        $changed = 0;

        foreach ($vendors as $vendor) {
            if ($vendor->category !== $category) {
                $this->classify($vendor, $category);
                $changed++;
            }
        }

        return $changed;
    }
}
