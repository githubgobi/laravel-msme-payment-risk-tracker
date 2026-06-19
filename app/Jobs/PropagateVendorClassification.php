<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Services\InvoiceRiskRecomputer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After a vendor's category changes, propagates the new category to all
 * existing non-paid invoices and re-runs the risk engine on each one.
 *
 * Runs in chunks of 200 to avoid memory exhaustion on large datasets.
 */
class PropagateVendorClassification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 3;

    public function __construct(
        public readonly Vendor $vendor,
    ) {}

    public function handle(InvoiceRiskRecomputer $recomputer): void
    {
        $vendor = $this->vendor;

        // Step 1: Stamp the new category onto all active invoices
        $updated = 0;
        PurchaseInvoice::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', [InvoiceStatus::Paid->value])
            ->whereNull('deleted_at')
            ->chunk(200, function ($invoices) use ($vendor, &$updated) {
                foreach ($invoices as $invoice) {
                    PurchaseInvoice::withoutGlobalScopes()
                        ->where('id', $invoice->id)
                        ->update(['vendor_category_snapshot' => $vendor->category->value]);
                    $updated++;
                }
            });

        // Step 2: Recompute risk (uses the fresh snapshot from step 1)
        $recomputed = $recomputer->recomputeForVendor($vendor);

        Log::info('Vendor classification propagated', [
            'vendor_id'  => $vendor->id,
            'category'   => $vendor->category->value,
            'snapshots'  => $updated,
            'recomputed' => $recomputed,
        ]);
    }
}
