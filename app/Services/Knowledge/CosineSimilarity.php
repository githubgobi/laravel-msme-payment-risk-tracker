<?php

namespace App\Services\Knowledge;

final class CosineSimilarity
{
    /**
     * Cosine similarity clamped to [0.0, 1.0].
     * Returns 0.0 on dimension mismatch or zero vector.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public function compute(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }

        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;

        foreach ($a as $i => $va) {
            $vb  = $b[$i];
            $dot += $va * $vb;
            $na  += $va * $va;
            $nb  += $vb * $vb;
        }

        if ($na === 0.0 || $nb === 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($na) * sqrt($nb))));
    }
}
