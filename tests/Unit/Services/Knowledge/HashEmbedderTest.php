<?php

namespace Tests\Unit\Services\Knowledge;

use App\Services\Knowledge\HashEmbedder;
use PHPUnit\Framework\TestCase;

class HashEmbedderTest extends TestCase
{
    private HashEmbedder $embedder;

    protected function setUp(): void
    {
        $this->embedder = new HashEmbedder();
    }

    public function test_returns_array_of_correct_dimension(): void
    {
        $vec = $this->embedder->embed('Ramco Cotton Traders');
        $this->assertCount(HashEmbedder::DIM, $vec);
    }

    public function test_all_elements_are_floats(): void
    {
        $vec = $this->embedder->embed('test vendor');
        foreach ($vec as $v) {
            $this->assertIsFloat($v);
        }
    }

    public function test_output_is_l2_normalised(): void
    {
        $vec  = $this->embedder->embed('Cotton Yarn Supplier Tiruppur');
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec)));
        $this->assertEqualsWithDelta(1.0, $norm, 1e-6);
    }

    public function test_empty_string_returns_zero_vector(): void
    {
        $vec = $this->embedder->embed('');
        $this->assertSame(array_fill(0, HashEmbedder::DIM, 0.0), $vec);
    }

    public function test_deterministic_output(): void
    {
        $text = 'Arjun Textiles Pvt Ltd';
        $this->assertSame($this->embedder->embed($text), $this->embedder->embed($text));
    }

    public function test_similar_texts_score_higher_than_unrelated(): void
    {
        $similarity = new \App\Services\Knowledge\CosineSimilarity();

        $cotton1 = $this->embedder->embed('Cotton Yarn Supplier');
        $cotton2 = $this->embedder->embed('Cotton Textile Yarn Trader');
        $unrelated = $this->embedder->embed('Hydraulic Pump Gear Shaft');

        $this->assertGreaterThan(
            $similarity->compute($cotton1, $unrelated),
            $similarity->compute($cotton1, $cotton2),
        );
    }
}
