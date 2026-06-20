<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Produces text embeddings for the knowledge base.
 *
 * Strategy (offline-first):
 *   1. Ollama /api/embeddings — tries nomic-embed-text (768-dim), then the
 *      configured chat model as fallback. Produces high-quality dense vectors.
 *   2. HashEmbedder — pure PHP, 256-dim, zero dependencies.
 *      Activated when Ollama is unreachable or returns an error.
 *
 * The active model name is stored per-document so mixed-dimension indexes
 * continue to work — KnowledgeRepository re-embeds the query to match each
 * stored chunk's dimension before computing cosine similarity.
 */
class EmbeddingService
{
    private const EMBED_MODEL_PREFERENCE = ['nomic-embed-text'];

    public function __construct(
        private readonly string      $endpoint,
        private readonly string      $chatModel,
        private readonly int         $timeout,
        private readonly HashEmbedder $hasher,
    ) {}

    /**
     * Embed text. Returns [float[], modelName].
     *
     * @return array{0: float[], 1: string}
     */
    public function embed(string $text): array
    {
        $models = array_unique([...self::EMBED_MODEL_PREFERENCE, $this->chatModel]);

        foreach ($models as $model) {
            $result = $this->ollamaEmbed($text, $model);
            if ($result !== null) {
                return [$result, $model];
            }
        }

        Log::debug('EmbeddingService: Ollama unavailable, using hash embedding');
        return [$this->hasher->embed($text), HashEmbedder::MODEL_NAME];
    }

    /**
     * Re-embed a query to match the dimension of a stored chunk.
     * Used when an index contains chunks from different embedding runs.
     *
     * @return float[]
     */
    public function embedToMatchDimension(string $text, int $targetDim): array
    {
        if ($targetDim === HashEmbedder::DIM) {
            return $this->hasher->embed($text);
        }

        // Try each Ollama model until one produces the right dimension
        $models = array_unique([...self::EMBED_MODEL_PREFERENCE, $this->chatModel]);
        foreach ($models as $model) {
            $vec = $this->ollamaEmbed($text, $model);
            if ($vec !== null && count($vec) === $targetDim) {
                return $vec;
            }
        }

        // Last resort: hash (may differ in dimension, CosineSimilarity will return 0.0)
        return $this->hasher->embed($text);
    }

    private function ollamaEmbed(string $text, string $model): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->endpoint}/api/embeddings", [
                    'model'  => $model,
                    'prompt' => $text,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $vec = $response->json('embedding');

            if (! is_array($vec) || empty($vec)) {
                return null;
            }

            return array_map('floatval', $vec);
        } catch (\Throwable $e) {
            Log::debug('EmbeddingService: Ollama embed failed', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
