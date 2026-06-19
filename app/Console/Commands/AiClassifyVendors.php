<?php

namespace App\Console\Commands;

use App\Enums\VendorCategory;
use App\Enums\VendorVerificationSource;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\Llm\VendorCategoryClassifier;
use App\Services\OllamaClient;
use App\Services\VendorClassificationService;
use Illuminate\Console\Command;

class AiClassifyVendors extends Command
{
    protected $signature = 'ai:classify-vendors
        {--tenant= : Restrict to a specific tenant ID}
        {--dry-run : Show what would be classified without persisting anything}
        {--force   : Apply even below the confidence threshold (use with caution)}';

    protected $description = 'Use AI (Ollama) to auto-classify unclassified vendors';

    public function handle(
        OllamaClient              $client,
        VendorCategoryClassifier  $classifier,
        VendorClassificationService $classificationService,
    ): int {
        if (! config('llm.enabled')) {
            $this->error('LLM is disabled. Set LLM_ENABLED=true in .env first.');
            return self::FAILURE;
        }

        if (! $client->isAvailable()) {
            $this->error("Ollama is not reachable at " . config('llm.endpoint') . ".");
            $this->line("Run: ollama serve && ollama pull " . config('llm.model'));
            return self::FAILURE;
        }

        $this->info("Using model: " . config('llm.model'));
        $this->info("Confidence threshold: " . (config('llm.confidence_threshold') * 100) . '%');

        $isDryRun = $this->option('dry-run');
        $isForce  = $this->option('force');

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be persisted.');
        }

        $query = Vendor::withoutGlobalScopes()
            ->where('category', VendorCategory::Unclassified)
            ->whereNull('deleted_at');

        if ($tenantId = $this->option('tenant')) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant #{$tenantId} not found.");
                return self::FAILURE;
            }
            $query->where('tenant_id', $tenantId);
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

        $rows = [];

        foreach ($vendors as $vendor) {
            $result = $classifier->classify(
                vendorName: $vendor->name,
                gstin:      $vendor->gstin,
                state:      $vendor->state,
            );

            if ($result === null) {
                $failed++;
                $rows[] = [$vendor->id, $vendor->name, '—', '—', 'LLM error'];
                $bar->advance();
                continue;
            }

            $shouldApply = $result->autoApplied || $isForce;
            $status      = match (true) {
                $isDryRun   => 'dry-run',
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

        $this->table(
            ['ID', 'Vendor Name', 'Category', 'Confidence', 'Status'],
            $rows,
        );

        $this->newLine();
        $this->info("Applied: {$applied} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }
}
