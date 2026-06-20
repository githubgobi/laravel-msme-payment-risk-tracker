<?php

namespace Tests\Unit\Prompts;

use App\Prompts\FuzzyMatchPrompt;
use PHPUnit\Framework\TestCase;

class FuzzyMatchPromptTest extends TestCase
{
    public function test_version_is_semver_string(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            FuzzyMatchPrompt::version()
        );
    }

    public function test_build_contains_imported_name(): void
    {
        $prompt = (new FuzzyMatchPrompt('Arjun Pvt Ltd', '1. ID:5 | Arjun Private Limited'))->build();
        $this->assertStringContainsString('Arjun Pvt Ltd', $prompt);
    }

    public function test_build_contains_candidate_list(): void
    {
        $candidates = "1. ID:5 | Arjun Private Limited\n2. ID:8 | Arjun Industries";
        $prompt     = (new FuzzyMatchPrompt('Arjun Pvt', $candidates))->build();
        $this->assertStringContainsString('Arjun Private Limited', $prompt);
        $this->assertStringContainsString('Arjun Industries', $prompt);
    }

    public function test_build_requests_json_output(): void
    {
        $prompt = (new FuzzyMatchPrompt('X', 'candidates'))->build();
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('"vendor_id"', $prompt);
        $this->assertStringContainsString('"confidence"', $prompt);
        $this->assertStringContainsString('"reasoning"', $prompt);
    }

    public function test_build_mentions_null_for_no_match(): void
    {
        $prompt = (new FuzzyMatchPrompt('X', 'candidates'))->build();
        $this->assertStringContainsString('null', $prompt);
    }

    public function test_build_mentions_indian_abbreviations(): void
    {
        $prompt = (new FuzzyMatchPrompt('X', 'candidates'))->build();
        $this->assertStringContainsString('Pvt', $prompt);
        $this->assertStringContainsString('Ltd', $prompt);
    }
}
