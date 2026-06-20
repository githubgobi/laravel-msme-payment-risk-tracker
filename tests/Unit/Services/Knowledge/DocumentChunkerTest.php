<?php

namespace Tests\Unit\Services\Knowledge;

use App\Services\Knowledge\DocumentChunker;
use PHPUnit\Framework\TestCase;

class DocumentChunkerTest extends TestCase
{
    private DocumentChunker $chunker;

    protected function setUp(): void
    {
        $this->chunker = new DocumentChunker(chunkSize: 100, chunkOverlap: 20);
    }

    public function test_empty_string_returns_empty_array(): void
    {
        $this->assertSame([], $this->chunker->chunk(''));
    }

    public function test_whitespace_only_returns_empty_array(): void
    {
        $this->assertSame([], $this->chunker->chunk('   '));
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $result = $this->chunker->chunk('Ramco Cotton Traders');
        $this->assertCount(1, $result);
        $this->assertSame('Ramco Cotton Traders', $result[0]);
    }

    public function test_text_exactly_at_limit_returns_single_chunk(): void
    {
        $text   = str_repeat('a', 100);
        $result = $this->chunker->chunk($text);
        $this->assertCount(1, $result);
    }

    public function test_long_text_produces_multiple_chunks(): void
    {
        $text   = str_repeat('word ', 100); // 500 chars
        $result = $this->chunker->chunk($text);
        $this->assertGreaterThan(1, count($result));
    }

    public function test_all_words_covered_by_chunks(): void
    {
        $text   = implode(' ', range(1, 200));
        $result = $this->chunker->chunk($text);

        $combined = implode(' ', $result);
        foreach (range(1, 200) as $n) {
            $this->assertStringContainsString((string) $n, $combined);
        }
    }

    public function test_no_empty_chunks_produced(): void
    {
        $text   = str_repeat('vendor record data ', 50);
        $result = $this->chunker->chunk($text);
        foreach ($result as $chunk) {
            $this->assertNotSame('', $chunk);
        }
    }
}
