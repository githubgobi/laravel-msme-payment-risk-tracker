<?php

namespace Tests\Unit\Services;

use App\Contracts\LlmClient;
use App\DTOs\LlmClassificationResult;
use App\Enums\VendorCategory;
use App\Services\Llm\VendorCategoryClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VendorCategoryClassifierTest extends TestCase
{
    private function makeClassifier(string $llmResponse, float $threshold = 0.80): VendorCategoryClassifier
    {
        $client = $this->createMock(LlmClient::class);
        $client->method('generate')->willReturn($llmResponse);

        return new VendorCategoryClassifier($client, $threshold);
    }

    #[Test]
    public function returns_classification_result_for_micro_vendor(): void
    {
        $classifier = $this->makeClassifier('{"category":"micro","confidence":0.92,"reasoning":"small workshop name"}');

        $result = $classifier->classify('Raju Weaving Works');

        $this->assertInstanceOf(LlmClassificationResult::class, $result);
        $this->assertSame(VendorCategory::Micro, $result->category);
        $this->assertSame(0.92, $result->confidence);
        $this->assertTrue($result->autoApplied);
    }

    #[Test]
    public function auto_applied_is_false_when_confidence_below_threshold(): void
    {
        $classifier = $this->makeClassifier('{"category":"small","confidence":0.65,"reasoning":"uncertain"}');

        $result = $classifier->classify('Some Ambiguous Pvt Ltd');

        $this->assertNotNull($result);
        $this->assertFalse($result->autoApplied);
        $this->assertSame(VendorCategory::Small, $result->category);
    }

    #[Test]
    public function returns_null_when_llm_client_unavailable(): void
    {
        $client = $this->createMock(LlmClient::class);
        $client->method('generate')->willReturn(null);

        $classifier = new VendorCategoryClassifier($client, 0.80);
        $result     = $classifier->classify('Any Vendor');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_on_invalid_json(): void
    {
        $classifier = $this->makeClassifier('not valid json');

        $result = $classifier->classify('Some Vendor');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_category_is_unrecognised(): void
    {
        $classifier = $this->makeClassifier('{"category":"enterprise","confidence":0.9,"reasoning":"wrong enum"}');

        $result = $classifier->classify('Big Corp Ltd');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_category_is_unclassified(): void
    {
        $classifier = $this->makeClassifier('{"category":"unclassified","confidence":0.5,"reasoning":"dunno"}');

        $result = $classifier->classify('Mystery Vendor');

        $this->assertNull($result);
    }

    #[Test]
    public function classifies_large_vendor_correctly(): void
    {
        $classifier = $this->makeClassifier('{"category":"large","confidence":0.97,"reasoning":"national bank"}');

        $result = $classifier->classify('State Bank of India');

        $this->assertSame(VendorCategory::Large, $result->category);
        $this->assertTrue($result->autoApplied);
    }

    #[Test]
    public function passes_gstin_and_state_to_prompt_context(): void
    {
        $client = $this->createMock(LlmClient::class);
        $client->expects($this->once())
            ->method('generate')
            ->with($this->stringContains('27AAPFU0939F1ZV'))
            ->willReturn('{"category":"micro","confidence":0.88,"reasoning":"with gstin"}');

        $classifier = new VendorCategoryClassifier($client, 0.80);
        $classifier->classify('Test Vendor', gstin: '27AAPFU0939F1ZV', state: 'Maharashtra');
    }
}
