<?php

namespace Tests\Unit\Services;

use App\Contracts\LlmClient;
use App\DTOs\LlmMatchResult;
use App\Services\Llm\VendorFuzzyMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VendorFuzzyMatcherTest extends TestCase
{
    private array $candidates = [
        ['id' => 1, 'name' => 'Arjun Textiles Private Limited', 'gstin' => '27AAPFU0939F1ZV', 'state' => 'Maharashtra'],
        ['id' => 2, 'name' => 'Raju Weaving Works',             'gstin' => null,                'state' => 'Tamil Nadu'],
        ['id' => 3, 'name' => 'Global Trading Co',              'gstin' => '06AAJCG1234A1ZK', 'state' => 'Haryana'],
    ];

    private function makeMatcher(string $llmResponse, float $threshold = 0.80): VendorFuzzyMatcher
    {
        $client = $this->createMock(LlmClient::class);
        $client->method('generate')->willReturn($llmResponse);

        return new VendorFuzzyMatcher($client, $threshold, 20);
    }

    #[Test]
    public function returns_match_result_when_llm_identifies_vendor(): void
    {
        $matcher = $this->makeMatcher('{"vendor_id":1,"confidence":0.95,"reasoning":"Arjun Tex = Arjun Textiles abbreviation"}');

        $result = $matcher->findBestMatch('Arjun Tex Pvt Ltd', $this->candidates);

        $this->assertInstanceOf(LlmMatchResult::class, $result);
        $this->assertSame(1, $result->vendorId);
        $this->assertSame('Arjun Textiles Private Limited', $result->vendorName);
        $this->assertSame(0.95, $result->confidence);
    }

    #[Test]
    public function returns_null_when_llm_returns_null_vendor_id(): void
    {
        $matcher = $this->makeMatcher('{"vendor_id":null,"confidence":0.1,"reasoning":"no match found"}');

        $result = $matcher->findBestMatch('Unknown Company XYZ', $this->candidates);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_confidence_below_threshold(): void
    {
        $matcher = $this->makeMatcher('{"vendor_id":2,"confidence":0.55,"reasoning":"weak match"}');

        $result = $matcher->findBestMatch('Raju Weave', $this->candidates);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_llm_client_returns_null(): void
    {
        $client = $this->createMock(LlmClient::class);
        $client->method('generate')->willReturn(null);

        $matcher = new VendorFuzzyMatcher($client, 0.80, 20);

        $result = $matcher->findBestMatch('Any Name', $this->candidates);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_llm_returns_invalid_json(): void
    {
        $matcher = $this->makeMatcher('this is not json at all');

        $result = $matcher->findBestMatch('Some Vendor', $this->candidates);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_llm_returns_unknown_vendor_id(): void
    {
        $matcher = $this->makeMatcher('{"vendor_id":999,"confidence":0.99,"reasoning":"hallucinated vendor"}');

        $result = $matcher->findBestMatch('Some Vendor', $this->candidates);

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_candidates_are_empty(): void
    {
        $client = $this->createMock(LlmClient::class);
        $client->expects($this->never())->method('generate');

        $matcher = new VendorFuzzyMatcher($client, 0.80, 20);

        $result = $matcher->findBestMatch('Any Name', []);

        $this->assertNull($result);
    }

    #[Test]
    public function respects_custom_confidence_threshold(): void
    {
        // confidence 0.75 is below default 0.80 but above custom 0.70
        $matcher = $this->makeMatcher(
            '{"vendor_id":3,"confidence":0.75,"reasoning":"reasonable match"}',
            threshold: 0.70,
        );

        $result = $matcher->findBestMatch('Global Traders', $this->candidates);

        $this->assertNotNull($result);
        $this->assertSame(3, $result->vendorId);
    }
}
