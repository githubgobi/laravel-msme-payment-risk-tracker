<?php

namespace App\Console\Commands;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\Knowledge\VendorIngester;
use App\Services\Llm\VendorCategoryClassifier;
use App\Services\OllamaClient;
use App\Services\VendorClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiClassifyVendors extends Command
{
    protected $signature = 'ai:classify-vendors
        {--tenant= : Restrict to a specific tenant ID}
        {--dry-run : Show what would be classified without persisting anything}
        {--force   : Apply even below the confidence threshold (use with caution)}';

    protected $description = 'Auto-classify unclassified vendors using the LLM and re-index the knowledge base';

    public function handle(
        OllamaClient              $client,
        VendorCategoryClassifier  $classifier,
        VendorClassificationService $classificationService,
        VendorIngester            $ingester,
    ): int {
        if (! config('llm.enabled')) {
            $this->error('LLM is disabled. Set LLM_ENABLED=true in .env first.');
            return self::FAILURE;
        }

        if (! $client->isAvailable()) {
            $this->error('Ollama is not reachable at ' . config('llm.endpoint') . '.');
            $this->line('Run: ollama serve && ollama pull ' . config('llm.model'));
            return self::FAILURE;
        }

        $this->info('Using model: ' . config('llm.model'));
        $this->info('Confidence threshold: ' . (config('llm.confidence_threshold') * 100) . '%');

        $isDryRun = (bool) $this->option('dry-run');
        $isForce  = (bool) $this->option('force');

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be persisted.');
        }

        $query = Vendor::withoutGlobalScopes()
            ->where('category', VendorCategory::Unclassified)
            ->whereNull('deleted_at');

        if ($tenantOption = $this->option('tenant')) {
            $tenant = Tenant::find($tenantOption);
            if (! $tenant) {
                $this->error("Tenant #{$tenantOption} not found.");
                return self::FAILURE;
            }
            $query->where('tenant_id', (int) $tenantOption);
            $this->info("Filtering to tenant: {$tenant->name}");
        }

        $vendors = $query->get();

        if ($vendors->isEmpty()) {
            $this->info('No unclassified vendors found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$vendors->count()} unclassified vendor(s)...");
        $this->newLine();

        $bar     = $this->output->createProgressBar($vendors->count());
        $applied = 0;
        $skipped = 0;
        $failed  = 0;
        $rows    = [];
        $affectedTenants = [];

        foreach ($vendors as $vendor) {
            try {
                $result = $classifier->classify(
                    vendorName: $vendor->name,
                    gstin:      $vendor->gstin,
                    state:      $vendor->state,
                    tenantId:   $vendor->tenant_id,
                );
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [$vendor->id, mb_strimwidth($vendor->name, 0, 35, '…'), '—', '—', 'exception'];
                Log::error('ai:classify-vendors exception', [
                    'vendor_id' => $vendor->id,
                    'error'     => $e->getMessage(),
                ]);
                $bar->advance();
                continue;
            }

            if ($result === null) {
                $failed++;
                $rows[] = [$vendor->id, mb_strimwidth($vendor->name, 0, 35, '…'), '—', '—', 'LLM error'];
                $bar->advance();
                continue;
            }

            $shouldApply = $result->autoApplied || $isForce;
            $status      = match (true) {
                $isDryRun    => 'dry-run',
                $shouldApply => 'applied',
                default      => 'below threshold',
            };

            if ($shouldApply && ! $isDryRun) {
                $classificationService->classify(
                    vendor:   $vendor,
                    category: $result->category,
                    source:   VendorVerificationSource::Llm,
                );

                $vendor->withoutGlobalScopes()->where('id', $vendor->id)->update([
                    'llm_confidence' => $result->confidence,
                    'llm_reasoning'  => $result->reasoning,
                ]);

                $affectedTenants[$vendor->tenant_id] = true;
                $applied++;
            } else {
                $skipped++;
            }

            $rows[] = [
                $vendor->id,
                mb_strimwidth($vendor->name, 0, 35, '…'),
                $result->category->label(),
                round($result->confidence * 100) . '%',
                $status,
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['ID', 'Vendor Name', 'Category', 'Confidence', 'Status'], $rows);

        $this->newLine();
        $this->info("Applied: {$applied} | Skipped: {$skipped} | Failed: {$failed}");

        // Re-index knowledge base for each tenant where vendors were auto-classified
        if (! $isDryRun && ! empty($affectedTenants)) {
            $this->newLine();
            $this->info('Re-indexing knowledge base...');
            foreach (array_keys($affectedTenants) as $tid) {
                $result = $ingester->ingestAll($tid, userId: null);
                $this->line(sprintf(
                    '  tenant #%d → indexed=%d skipped=%d',
                    $tid,
                    $result['indexed'],
                    $result['skipped'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
