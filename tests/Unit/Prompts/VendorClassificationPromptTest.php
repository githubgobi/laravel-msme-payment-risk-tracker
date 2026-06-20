<?php

namespace Tests\Unit\Prompts;

use App\Prompts\VendorClassificationPrompt;
use PHPUnit\Framework\TestCase;

class VendorClassificationPromptTest extends TestCase
{
    public function test_version_is_semver_string(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            VendorClassificationPrompt::version()
        );
    }

    public function test_build_contains_vendor_name(): void
    {
        $prompt = (new VendorClassificationPrompt('Arjun Cotton Mills'))->build();
        $this->assertStringContainsString('Arjun Cotton Mills', $prompt);
    }

    public function test_build_contains_udyam_categories(): void
    {
        $prompt = (new VendorClassificationPrompt('Test Vendor'))->build();
        $this->assertStringContainsString('micro', $prompt);
        $this->assertStringContainsString('small', $prompt);
        $this->assertStringContainsString('medium', $prompt);
        $this->assertStringContainsString('large', $prompt);
    }

    public function test_build_requests_json_output(): void
    {
        $prompt = (new VendorClassificationPrompt('Test Vendor'))->build();
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('"category"', $prompt);
        $this->assertStringContainsString('"confidence"', $prompt);
        $this->assertStringContainsString('"reasoning"', $prompt);
    }

    public function test_build_includes_context_when_provided(): void
    {
        $prompt = (new VendorClassificationPrompt(
            vendorName: 'Test Vendor',
            context:    "GSTIN: 33ABCDE1234F1Z5\n",
        ))->build();
        $this->assertStringContainsString('33ABCDE1234F1Z5', $prompt);
    }

    public function test_build_includes_rag_section_when_provided(): void
    {
        $rag    = "1. Ramco Cotton Traders — micro (92%)";
        $prompt = (new VendorClassificationPrompt(
            vendorName:  'Test Vendor',
            ragSection:  $rag,
        ))->build();
        $this->assertStringContainsString('Ramco Cotton Traders', $prompt);
    }

    public function test_same_version_across_instances(): void
    {
        $a = VendorClassificationPrompt::version();
        $b = (new VendorClassificationPrompt('X'))->build();
        $this->assertNotEmpty($a);
        // version doesn't change between instances
        $this->assertSame($a, VendorClassificationPrompt::version());
    }
}
