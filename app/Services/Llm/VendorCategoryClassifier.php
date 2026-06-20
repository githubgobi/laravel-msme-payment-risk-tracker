<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\DTOs\LlmClassificationResult;
use App\Enums\VendorCategory;
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
        ?string $gstin = null,
        ?string $state = null,
    ): ?LlmClassificationResult {
        $contextLines = [];
        if ($gstin) {
            $stateCode     = substr($gstin, 0, 2);
            $contextLines[] = "GSTIN: {$gstin} (state code: {$stateCode})";
        }
        if ($state) {
            $contextLines[] = "State: {$state}";
        }
        $context = $contextLines ? implode("\n", $contextLines) . "\n" : '';

        // Retrieve similar vendors already classified in this tenant's database
        $ragSection = '';
        if ($this->ragContext !== null && $this->tenantId !== null) {
            $ragText = $this->ragContext->getVendorContext($this->tenantId, $vendorName);
            if ($ragText !== '') {
                $ragSection = "\n{$ragText}\n";
            }
        }

        $prompt = <<<PROMPT
You are an MSME classification assistant for India's Udyam registration system.

Udyam categories by annual turnover:
- micro:   Turnover ≤ ₹5 crore
- small:   Turnover ≤ ₹50 crore
- medium:  Turnover ≤ ₹250 crore
- large:   Turnover > ₹250 crore (NOT covered by Section 43B(h))

Vendor name: "{$vendorName}"
{$context}{$ragSection}
Classify this vendor based on its business name, any available context, and the known vendor examples above.

Guidelines:
- "Works", "Industries", "Traders", "Enterprises", "Supplier" in the name → likely micro or small
- National brands, banks, MNCs, listed companies → large
- When genuinely uncertain, prefer micro or small (conservative for tax compliance)
- Return only one of: micro, small, medium, large

Respond ONLY with valid JSON. Do not include any other text.

{"category": "micro|small|medium|large", "confidence": <float 0.0 to 1.0>, "reasoning": "<one sentence>"}
PROMPT;

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
