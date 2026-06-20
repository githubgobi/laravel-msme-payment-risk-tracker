<?php

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\DTOs\LlmMatchResult;
use App\Prompts\FuzzyMatchPrompt;
use Illuminate\Support\Facades\Log;

/**
 * Uses an LLM to find the best matching existing vendor for an imported name
 * when exact/GSTIN/Udyam matching fails.
 *
 * Handles common Indian business name variations:
 *   - Pvt / Private, Ltd / Limited, Co / Company
 *   - Spelling differences, abbreviations, transliteration variants
 *
 * Returns null when:
 *   - Ollama is unavailable
 *   - LLM returns invalid JSON
 *   - No candidate is a plausible match (vendor_id: null in response)
 *   - Confidence is below the configured threshold
 */
class VendorFuzzyMatcher
{
    public function __construct(
        private readonly LlmClient $client,
        private readonly float        $confidenceThreshold,
        private readonly int          $maxCandidates,
    ) {}

    /**
     * Find the best matching vendor for the imported name.
     *
     * @param  string  $importedName   Name from the import file
     * @param  array   $candidates     Array of ['id', 'name', 'gstin', 'state'] maps
     * @return LlmMatchResult|null     null means "no confident match — create new vendor"
     */
    public function findBestMatch(string $importedName, array $candidates): ?LlmMatchResult
    {
        if (empty($candidates)) {
            return null;
        }

        $candidateList = collect(array_slice($candidates, 0, $this->maxCandidates))
            ->map(fn ($c, $i) => sprintf(
                '%d. ID:%d | %s%s',
                $i + 1,
                $c['id'],
                $c['name'],
                isset($c['gstin']) && $c['gstin'] ? " | GSTIN: {$c['gstin']}" : '',
            ))
            ->implode("\n");

        $prompt = (new FuzzyMatchPrompt($importedName, $candidateList))->build();

        $raw = $this->client->generate($prompt);

        if ($raw === null) {
            return null;
        }

        return $this->parseMatchResponse($raw, $candidates);
    }

    private function parseMatchResponse(string $raw, array $candidates): ?LlmMatchResult
    {
        $data = json_decode(trim($raw), true);

        if (! is_array($data)) {
            Log::warning('VendorFuzzyMatcher: invalid JSON from LLM', ['raw' => $raw]);
            return null;
        }

        $vendorId   = $data['vendor_id'] ?? null;
        $confidence = (float) ($data['confidence'] ?? 0.0);
        $reasoning  = (string) ($data['reasoning'] ?? '');

        if ($vendorId === null) {
            return null;
        }

        if ($confidence < $this->confidenceThreshold) {
            Log::debug('VendorFuzzyMatcher: confidence below threshold', [
                'confidence' => $confidence,
                'threshold'  => $this->confidenceThreshold,
                'reasoning'  => $reasoning,
            ]);
            return null;
        }

        // Validate that the returned vendor_id is actually in our candidate list
        $match = collect($candidates)->firstWhere('id', (int) $vendorId);

        if (! $match) {
            Log::warning('VendorFuzzyMatcher: LLM returned unknown vendor_id', [
                'vendor_id' => $vendorId,
            ]);
            return null;
        }

        return new LlmMatchResult(
            vendorId:   (int) $vendorId,
            vendorName: $match['name'],
            confidence: $confidence,
            reasoning:  $reasoning,
        );
    }
}
