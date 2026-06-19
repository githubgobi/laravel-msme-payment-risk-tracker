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
use App\Services\Llm\VendorCategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmClassifyControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $owner;
    private Vendor $vendor;

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

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);

        $this->vendor = Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Raju Weaving Works',
            'category'  => VendorCategory::Unclassified,
        ]);
    }

    // ─── /vendors/ai-review ──────────────────────────────────────────────────

    #[Test]
    public function ai_review_page_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->get('/vendors/ai-review');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/AiReview')
            ->has('vendors')
            ->has('llmModel')
            ->has('threshold')
        );
    }

    #[Test]
    public function ai_review_returns_404_when_llm_disabled(): void
    {
        config(['llm.enabled' => false]);

        $response = $this->actingAs($this->owner)->get('/vendors/ai-review');

        $response->assertStatus(404);
    }

    #[Test]
    public function ai_review_lists_only_unclassified_vendors(): void
    {
        Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Classified Vendor',
            'category'  => VendorCategory::Micro,
        ]);

        $response = $this->actingAs($this->owner)->get('/vendors/ai-review');

        $response->assertInertia(fn ($page) => $page
            ->has('vendors', 1)
            ->where('vendors.0.name', 'Raju Weaving Works')
        );
    }

    // ─── /vendors/{vendor}/ai-classify ────────────────────────────────────────

    #[Test]
    public function suggest_returns_classification_json(): void
    {
        $mockResult = new LlmClassificationResult(
            category:    VendorCategory::Micro,
            confidence:  0.92,
            reasoning:   'Small weaving workshop name',
            autoApplied: true,
        );

        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn($mockResult);

        $response = $this->actingAs($this->owner)
            ->postJson("/vendors/{$this->vendor->id}/ai-classify");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'category'    => 'micro',
                'confidence'  => 0.92,
                'auto_applied' => false,
            ]);
    }

    #[Test]
    public function suggest_returns_503_when_llm_unavailable(): void
    {
        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn(null);

        $response = $this->actingAs($this->owner)
            ->postJson("/vendors/{$this->vendor->id}/ai-classify");

        $response->assertStatus(503);
        $this->assertStringContainsString('Ollama', $response->json('error') ?? '');
    }

    #[Test]
    public function suggest_returns_404_when_llm_disabled(): void
    {
        config(['llm.enabled' => false]);

        $response = $this->actingAs($this->owner)
            ->postJson("/vendors/{$this->vendor->id}/ai-classify");

        $response->assertStatus(404);
    }

    // ─── /vendors/{vendor}/ai-classify/apply ──────────────────────────────────

    #[Test]
    public function apply_persists_category_and_llm_audit_fields(): void
    {
        $response = $this->actingAs($this->owner)
            ->post("/vendors/{$this->vendor->id}/ai-classify/apply", [
                'category'   => 'micro',
                'confidence' => 0.92,
                'reasoning'  => 'Small weaving workshop',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vendors', [
            'id'             => $this->vendor->id,
            'category'       => 'micro',
            'llm_confidence' => 0.920,
            'llm_reasoning'  => 'Small weaving workshop',
        ]);
    }

    #[Test]
    public function apply_validates_category_enum(): void
    {
        $response = $this->actingAs($this->owner)
            ->post("/vendors/{$this->vendor->id}/ai-classify/apply", [
                'category'   => 'enterprise',
                'confidence' => 0.9,
            ]);

        $response->assertSessionHasErrors('category');
    }

    // ─── /vendors/ai-classify-batch ──────────────────────────────────────────

    #[Test]
    public function batch_returns_summary_json(): void
    {
        $mockResult = new LlmClassificationResult(
            category:    VendorCategory::Small,
            confidence:  0.88,
            reasoning:   'Trading enterprise name',
            autoApplied: true,
        );

        $this->mock(VendorCategoryClassifier::class)
            ->shouldReceive('classify')
            ->andReturn($mockResult);

        $response = $this->actingAs($this->owner)
            ->postJson('/vendors/ai-classify-batch');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => ['total', 'applied', 'suggested', 'failed'],
                'results',
            ]);
    }

    // ─── /ai/status ──────────────────────────────────────────────────────────

    #[Test]
    public function status_endpoint_returns_llm_config(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/ai/status');

        $response->assertStatus(200)
            ->assertJsonStructure(['enabled', 'available', 'endpoint', 'model', 'threshold']);
    }
}
