<?php

namespace Tests\Feature;

use App\DTOs\LlmClassificationResult;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Prompts\VendorClassificationPrompt;
use App\Services\Llm\VendorCategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptVersionHeaderTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['llm.enabled' => true]);

        $tenant = Tenant::factory()->create([
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);

        $this->vendor = Vendor::factory()->create([
            'tenant_id' => $tenant->id,
            'category'  => VendorCategory::Unclassified,
        ]);
    }

    public function test_suggest_response_contains_prompt_version_header(): void
    {
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn(new LlmClassificationResult(
                category:    VendorCategory::Micro,
                confidence:  0.92,
                reasoning:   'Small weaving workshop',
                autoApplied: true,
            ));

        $response = $this->actingAs($this->user)
            ->postJson("/vendors/{$this->vendor->id}/ai-classify");

        $response->assertOk();
        $response->assertHeader('X-Prompt-Version', VendorClassificationPrompt::VERSION);
    }

    public function test_suggest_returns_version_header_even_on_llm_error(): void
    {
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn(null);

        $response = $this->actingAs($this->user)
            ->postJson("/vendors/{$this->vendor->id}/ai-classify");

        $response->assertStatus(503);
        $response->assertHeader('X-Prompt-Version', VendorClassificationPrompt::VERSION);
    }

    public function test_batch_response_contains_prompt_version_header(): void
    {
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->andReturn(new LlmClassificationResult(
                category:    VendorCategory::Small,
                confidence:  0.88,
                reasoning:   'Company name suggests small scale',
                autoApplied: false,
            ));

        $response = $this->actingAs($this->user)
            ->postJson('/vendors/ai-classify-batch');

        $response->assertOk();
        $response->assertHeader('X-Prompt-Version', VendorClassificationPrompt::VERSION);
    }

    public function test_prompt_version_is_semver_format(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            VendorClassificationPrompt::VERSION
        );
    }
}
