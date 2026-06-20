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
use App\Services\Knowledge\VendorIngester;
use App\Services\Llm\VendorCategoryClassifier;
use App\Services\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiClassifyVendorsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['llm.enabled' => true]);

        $this->tenant = Tenant::factory()->create([
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);
    }

    public function test_exits_early_when_llm_disabled(): void
    {
        config(['llm.enabled' => false]);

        $this->artisan('ai:classify-vendors')
            ->assertFailed()
            ->expectsOutput('LLM is disabled. Set LLM_ENABLED=true in .env first.');
    }

    public function test_exits_early_when_ollama_unavailable(): void
    {
        $this->mock(OllamaClient::class)
            ->shouldReceive('isAvailable')->once()->andReturn(false);

        $this->artisan('ai:classify-vendors')->assertFailed();
    }

    public function test_no_vendors_produces_info_message(): void
    {
        $this->mockOllamaAvailable();

        $this->artisan('ai:classify-vendors')
            ->assertSuccessful()
            ->expectsOutput('No unclassified vendors found.');
    }

    public function test_applies_high_confidence_classification(): void
    {
        $vendor = $this->createUnclassifiedVendor('Arjun Cotton Mills');

        $result = new LlmClassificationResult(
            category:    VendorCategory::Micro,
            confidence:  0.92,
            reasoning:   'Small cotton mill',
            autoApplied: true,
        );

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn($result);

        $this->mock(VendorIngester::class)
            ->shouldReceive('ingestAll')
            ->once()
            ->with($this->tenant->id, null)
            ->andReturn(['indexed' => 1, 'skipped' => 0]);

        $this->artisan('ai:classify-vendors')->assertSuccessful();

        $this->assertDatabaseHas('vendors', [
            'id'       => $vendor->id,
            'category' => VendorCategory::Micro->value,
        ]);
    }

    public function test_skips_low_confidence_below_threshold(): void
    {
        $this->createUnclassifiedVendor('Mystery Trading Co');

        $result = new LlmClassificationResult(
            category:    VendorCategory::Medium,
            confidence:  0.40,
            reasoning:   'Uncertain',
            autoApplied: false,
        );

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')->once()->andReturn($result);

        $this->mock(VendorIngester::class)
            ->shouldNotReceive('ingestAll');

        $this->artisan('ai:classify-vendors')->assertSuccessful();

        $this->assertDatabaseHas('vendors', [
            'category' => VendorCategory::Unclassified->value,
        ]);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $vendor = $this->createUnclassifiedVendor('Ramco Spinners');

        $result = new LlmClassificationResult(
            category:    VendorCategory::Small,
            confidence:  0.91,
            reasoning:   'Spinning company',
            autoApplied: true,
        );

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')->once()->andReturn($result);

        $this->mock(VendorIngester::class)->shouldNotReceive('ingestAll');

        $this->artisan('ai:classify-vendors --dry-run')
            ->assertSuccessful()
            ->expectsOutput('DRY RUN — no changes will be persisted.');

        $this->assertDatabaseHas('vendors', [
            'id'       => $vendor->id,
            'category' => VendorCategory::Unclassified->value,
        ]);
    }

    public function test_force_applies_below_threshold(): void
    {
        $vendor = $this->createUnclassifiedVendor('Niche Works Ltd');

        $result = new LlmClassificationResult(
            category:    VendorCategory::Small,
            confidence:  0.55,
            reasoning:   'Works suffix',
            autoApplied: false,
        );

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')->once()->andReturn($result);

        $this->mock(VendorIngester::class)
            ->shouldReceive('ingestAll')->once()->andReturn(['indexed' => 1, 'skipped' => 0]);

        $this->artisan('ai:classify-vendors --force')->assertSuccessful();

        $this->assertDatabaseHas('vendors', [
            'id'       => $vendor->id,
            'category' => VendorCategory::Small->value,
        ]);
    }

    public function test_tenant_option_filters_correctly(): void
    {
        $otherTenant = Tenant::factory()->create([
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        Vendor::factory()->create([
            'tenant_id' => $otherTenant->id,
            'category'  => VendorCategory::Unclassified,
            'is_active' => true,
        ]);

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)->shouldNotReceive('classify');

        $this->artisan("ai:classify-vendors --tenant={$this->tenant->id}")
            ->assertSuccessful()
            ->expectsOutput('No unclassified vendors found.');
    }

    public function test_llm_error_is_counted_as_failure_not_crash(): void
    {
        $this->createUnclassifiedVendor('Some Vendor');

        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')->once()->andReturn(null);

        $this->mock(VendorIngester::class)->shouldNotReceive('ingestAll');

        $this->artisan('ai:classify-vendors')
            ->assertSuccessful();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createUnclassifiedVendor(string $name): Vendor
    {
        return Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => $name,
            'category'  => VendorCategory::Unclassified,
            'is_active' => true,
        ]);
    }

    private function mockOllamaAvailable(): void
    {
        $this->mock(OllamaClient::class)->shouldReceive('isAvailable')->andReturn(true);
    }
}
