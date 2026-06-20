<?php

namespace Tests\Unit\Services\Knowledge;

use App\Services\Knowledge\CosineSimilarity;
use PHPUnit\Framework\TestCase;

class CosineSimilarityTest extends TestCase
{
    private CosineSimilarity $sim;

    protected function setUp(): void
    {
        $this->sim = new CosineSimilarity();
    }

    public function test_identical_vectors_return_one(): void
    {
        $v = [1.0, 0.0, 0.0];
        $this->assertEqualsWithDelta(1.0, $this->sim->compute($v, $v), 1e-9);
    }

    public function test_orthogonal_vectors_return_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->sim->compute([1.0, 0.0], [0.0, 1.0]), 1e-9);
    }

    public function test_opposite_vectors_clamped_to_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->sim->compute([1.0, 0.0], [-1.0, 0.0]), 1e-9);
    }

    public function test_dimension_mismatch_returns_zero(): void
    {
        $this->assertSame(0.0, $this->sim->compute([1.0, 0.0], [1.0, 0.0, 0.0]));
    }

    public function test_empty_arrays_return_zero(): void
    {
        $this->assertSame(0.0, $this->sim->compute([], []));
    }

    public function test_zero_vector_returns_zero(): void
    {
        $this->assertSame(0.0, $this->sim->compute([0.0, 0.0], [1.0, 0.0]));
    }

    public function test_result_is_between_zero_and_one(): void
    {
        $a = [0.5, 0.3, 0.8];
        $b = [0.1, 0.9, 0.4];
        $score = $this->sim->compute($a, $b);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }
}
