<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceType;

final class RagContextBuilder
{
    public function __construct(
        private readonly KnowledgeRepository $repository,
    ) {}

    /**
     * Build a formatted context string for vendor classification.
     *
     * Retrieves the top-k most semantically similar vendor records from this
     * tenant's knowledge base and formats them for injection into the LLM prompt.
     * Returns an empty string when the knowledge base has no vendor data.
     */
    public function getVendorContext(int $tenantId, string $vendorName, int $topK = 3): string
    {
        $hits = $this->repository->search(
            tenantId:   $tenantId,
            query:      $vendorName,
            topK:       $topK,
            sourceType: KnowledgeSourceType::Vendor,
        );

        if (empty($hits)) {
            return '';
        }

        $lines = ['Known classified vendors from this business (most similar first):'];

        foreach ($hits as $i => $hit) {
            $lines[] = sprintf('%d. %s (relevance: %d%%)', $i + 1, $hit['text'], (int) round($hit['score'] * 100));
        }

        return implode("\n", $lines);
    }

    /**
     * General-purpose context retrieval — searches across all source types.
     */
    public function getContext(int $tenantId, string $query, int $topK = 3): string
    {
        $hits = $this->repository->search(
            tenantId: $tenantId,
            query:    $query,
            topK:     $topK,
        );

        if (empty($hits)) {
            return '';
        }

        $lines = ['[Relevant records from your knowledge base:]'];

        foreach ($hits as $i => $hit) {
            $lines[] = sprintf(
                "\n[%d] %s (relevance: %d%%):\n%s",
                $i + 1,
                $hit['document_title'],
                (int) round($hit['score'] * 100),
                $hit['text'],
            );
        }

        return implode("\n", $lines);
    }
}
