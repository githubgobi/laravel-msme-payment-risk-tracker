<?php

namespace Tests\Feature;

use App\Enums\KnowledgeSourceType;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Knowledge\CosineSimilarity;
use App\Services\Knowledge\DocumentChunker;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\HashEmbedder;
use App\Services\Knowledge\KnowledgeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                    => 'Knowledge Test Co',
            'email'                   => 'kb@test.com',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->user = User::create([
            'name'      => 'KB Owner',
            'email'     => 'owner@kbtest.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'role'      => UserRole::Owner->value,
            'is_active' => true,
        ]);

        config(['llm.enabled' => true]);

        // Swap EmbeddingService for a deterministic hash-only version in tests
        $this->app->bind(EmbeddingService::class, fn () => new class(
            endpoint: 'http://localhost:11434',
            chatModel: 'test',
            timeout: 1,
            hasher: new HashEmbedder(),
        ) extends EmbeddingService {
            public function embed(string $text): array
            {
                return [(new HashEmbedder())->embed($text), HashEmbedder::MODEL_NAME];
            }
            public function embedToMatchDimension(string $text, int $targetDim): array
            {
                return (new HashEmbedder())->embed($text);
            }
        });
    }

    public function test_stats_returns_empty_counts_initially(): void
    {
        $this->actingAs($this->user)
            ->getJson('/knowledge/stats')
            ->assertOk()
            ->assertJsonFragment(['total_documents' => 0, 'total_chunks' => 0]);
    }

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/knowledge/stats')->assertRedirect('/login');
    }

    public function test_search_returns_empty_on_empty_knowledge_base(): void
    {
        $this->actingAs($this->user)
            ->postJson('/knowledge/search', ['query' => 'cotton yarn'])
            ->assertOk()
            ->assertJsonFragment(['results' => []]);
    }

    public function test_search_validates_required_query(): void
    {
        $this->actingAs($this->user)
            ->postJson('/knowledge/search', [])
            ->assertUnprocessable();
    }

    public function test_search_returns_ranked_results(): void
    {
        $this->seedVendorDocument('Ramco Cotton Traders | Category: Micro | State: Tamil Nadu');
        $this->seedVendorDocument('Reliance Industries Limited | Category: Large | State: Gujarat');

        $response = $this->actingAs($this->user)
            ->postJson('/knowledge/search', ['query' => 'cotton trader Tamil Nadu', 'top_k' => 1])
            ->assertOk();

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('text', $results[0]);
    }

    public function test_search_respects_top_k(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedVendorDocument("Vendor {$i} | Category: Small | State: Tamil Nadu");
        }

        $results = $this->actingAs($this->user)
            ->postJson('/knowledge/search', ['query' => 'vendor small Tamil Nadu', 'top_k' => 2])
            ->assertOk()
            ->json('results');

        $this->assertLessThanOrEqual(2, count($results));
    }

    public function test_ingest_vendors_indexes_classified_vendors(): void
    {
        Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Arjun Cotton Mills',
            'category'  => VendorCategory::Small,
            'state'     => 'Tamil Nadu',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/knowledge/ingest/vendors')
            ->assertOk()
            ->assertJsonFragment(['indexed' => 1, 'skipped' => 0]);

        $this->assertDatabaseHas('knowledge_documents', [
            'tenant_id'   => $this->tenant->id,
            'source_type' => KnowledgeSourceType::Vendor->value,
        ]);
    }

    public function test_ingest_vendors_skips_unclassified(): void
    {
        Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category'  => VendorCategory::Unclassified,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/knowledge/ingest/vendors')
            ->assertOk()
            ->assertJsonFragment(['indexed' => 0]);
    }

    public function test_ingest_is_idempotent(): void
    {
        Vendor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category'  => VendorCategory::Micro,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)->postJson('/knowledge/ingest/vendors');
        $this->actingAs($this->user)->postJson('/knowledge/ingest/vendors');

        $this->assertDatabaseCount('knowledge_documents', 1);
    }

    public function test_destroy_deletes_document_and_chunks(): void
    {
        $doc = $this->seedVendorDocument('Test vendor content');

        $this->actingAs($this->user)
            ->deleteJson("/knowledge/{$doc->id}")
            ->assertOk()
            ->assertJsonFragment(['deleted' => true]);

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('knowledge_chunks', ['document_id' => $doc->id]);
    }

    public function test_destroy_returns_404_for_unknown_document(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/knowledge/99999')
            ->assertNotFound();
    }

    public function test_tenant_isolation_in_search(): void
    {
        $otherTenant = Tenant::create([
            'name'                    => 'Other Co',
            'email'                   => 'other@test.com',
            'plan'                    => TenantPlan::Starter->value,
            'subscription_status'     => TenantStatus::Active->value,
            'rbi_bank_rate'           => 6.75,
            'is_active'               => true,
            'onboarding_completed_at' => now(),
        ]);

        // Seed a document for the other tenant directly
        $otherDoc = KnowledgeDocument::withoutGlobalScopes()->create([
            'tenant_id'       => $otherTenant->id,
            'title'           => 'Other Tenant Vendor',
            'source_type'     => KnowledgeSourceType::Vendor,
            'content'         => 'cotton yarn supplier',
            'chunk_count'     => 0,
            'embedding_model' => 'hash',
        ]);

        $results = $this->actingAs($this->user)
            ->postJson('/knowledge/search', ['query' => 'cotton yarn supplier'])
            ->assertOk()
            ->json('results');

        foreach ($results as $result) {
            $this->assertNotEquals($otherDoc->id, $result['document_id']);
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function seedVendorDocument(string $content): KnowledgeDocument
    {
        $hasher = new HashEmbedder();
        [$vec]  = [$hasher->embed($content), HashEmbedder::MODEL_NAME];

        $doc = KnowledgeDocument::withoutGlobalScopes()->create([
            'tenant_id'       => $this->tenant->id,
            'title'           => 'Test: ' . substr($content, 0, 30),
            'source_type'     => KnowledgeSourceType::Vendor,
            'content'         => $content,
            'chunk_count'     => 1,
            'embedding_model' => HashEmbedder::MODEL_NAME,
        ]);

        KnowledgeChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'text'        => $content,
            'embedding'   => $vec,
        ]);

        return $doc;
    }
}
