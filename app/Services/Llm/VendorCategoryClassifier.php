<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\DTOs\LlmClassificationResult;
use App\Enums\VendorCategory;
use App\Prompts\VendorClassificationPrompt;
use App\Services\Knowledge\RagContextBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Uses an LLM to suggest an MSME category for an unclassified vendor based
 * on its business name, GSTIN state prefix, and any other available signals.
 *
 * When a RagContextBuilder is provided, the prompt is enriched with real
 * examples of similarly-named vendors already classified in this tenant's
 * database, improving accuracy over generic rules alone.
 *
 * Udyam turnover thresholds (as of FY 2021-22):
 *   Micro  : Turnover ≤ ₹5 crore
 *   Small  : Turnover ≤ ₹50 crore
 *   Medium : Turnover ≤ ₹250 crore
 *   Large  : Turnover > ₹250 crore (not covered by Section 43B(h))
 *
 * When uncertain the LLM is instructed to prefer Micro or Small — this is the
 * conservative choice for tax compliance: wrongly tagging a Large vendor as
 * Small causes a missed disallowance, not an incorrect one.
 */
class VendorCategoryClassifier
{
    public function __construct(
        private readonly LlmClient          $client,
        private readonly float              $confidenceThreshold,
        private readonly ?RagContextBuilder $ragContext = null,
        private readonly ?int               $tenantId   = null,
    ) {}

    /**
     * Suggest an MSME category for the given vendor.
     *
     * @param  string       $vendorName
     * @param  string|null  $gstin      First 2 chars = state code (used as context)
     * @param  string|null  $state
     * @return LlmClassificationResult|null  null = LLM unavailable or unparseable
     */
    public function classify(
        string  $vendorName,
        ?string $gstin     = null,
        ?string $state     = null,
        ?int    $tenantId  = null,
    ): ?LlmClassificationResult {
        $contextLines = [];
        if ($gstin) {
            $stateCode      = substr($gstin, 0, 2);
            $contextLines[] = "GSTIN: {$gstin} (state code: {$stateCode})";
        }
        if ($state) {
            $contextLines[] = "State: {$state}";
        }
        $context = $contextLines ? implode("\n", $contextLines) . "\n" : '';

        $effectiveTenantId = $tenantId ?? $this->tenantId;

        $ragSection = '';
        if ($this->ragContext !== null && $effectiveTenantId !== null) {
            $ragText = $this->ragContext->getVendorContext($effectiveTenantId, $vendorName);
            if ($ragText !== '') {
                $ragSection = "\n{$ragText}\n";
            }
        }

        $prompt = (new VendorClassificationPrompt($vendorName, $context, $ragSection))->build();

        $raw = $this->client->generate($prompt);

        if ($raw === null) {
            return null;
        }

        return $this->parseClassificationResponse($raw);
    }

    private function parseClassificationResponse(string $raw): ?LlmClassificationResult
    {
        $data = json_decode(trim($raw), true);

        if (! is_array($data)) {
            Log::warning('VendorCategoryClassifier: invalid JSON from LLM', ['raw' => $raw]);
            return null;
        }

        $categoryRaw = strtolower(trim($data['category'] ?? ''));
        $confidence  = (float) ($data['confidence'] ?? 0.0);
        $reasoning   = (string) ($data['reasoning'] ?? '');

        $category = VendorCategory::tryFrom($categoryRaw);

        if (! $category || $category === VendorCategory::Unclassified) {
            Log::warning('VendorCategoryClassifier: unrecognised category', [
                'raw_category' => $categoryRaw,
            ]);
            return null;
        }

        $autoApplied = $confidence >= $this->confidenceThreshold;

        return new LlmClassificationResult(
            category:    $category,
            confidence:  $confidence,
            reasoning:   $reasoning,
            autoApplied: $autoApplied,
        );
    }
}
