<?php

namespace App\Services\Knowledge;

final class DocumentChunker
{
    public function __construct(
        private readonly int $chunkSize    = 500,
        private readonly int $chunkOverlap = 100,
    ) {}

    /**
     * Split text into overlapping character-based chunks.
     * Snaps boundaries to the nearest sentence or word to avoid mid-word cuts.
     * Returns an empty array for blank input.
     *
     * @return string[]
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start  = 0;
        $len    = mb_strlen($text);

        while ($start < $len) {
            $end = min($start + $this->chunkSize, $len);

            // Snap to sentence boundary first, then word boundary
            if ($end < $len) {
                $snap = mb_strrpos(mb_substr($text, $start, $end - $start), '. ');
                if ($snap === false || $snap <= 0) {
                    $snap = mb_strrpos(mb_substr($text, $start, $end - $start), ' ');
                }
                if ($snap !== false && $snap > 0) {
                    $end = $start + $snap + 1;
                }
            }

            $chunk = trim(mb_substr($text, $start, $end - $start));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($end >= $len) {
                break;
            }

            $start = max($start + 1, $end - $this->chunkOverlap);
        }

        return $chunks;
    }
}
