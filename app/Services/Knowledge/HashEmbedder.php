<?php

namespace App\Services\Knowledge;

/**
 * Deterministic 256-dim word-frequency embedding.
 * No external dependencies — works fully offline.
 *
 * Each dimension is keyed by hash(word) % 256.
 * TF weighting + L2 normalisation makes output suitable for cosine similarity.
 */
final class HashEmbedder
{
    public const DIM = 256;
    public const MODEL_NAME = 'hash';

    /**
     * @return float[]  L2-normalised 256-dim vector, or zero vector for blank input.
     */
    public function embed(string $text): array
    {
        $vec   = array_fill(0, self::DIM, 0.0);
        $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return $vec;
        }

        foreach ($words as $word) {
            $idx        = abs(crc32($word)) % self::DIM;
            $vec[$idx] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec)));

        if ($norm > 0.0) {
            $vec = array_map(fn ($x) => $x / $norm, $vec);
        }

        return $vec;
    }
}
