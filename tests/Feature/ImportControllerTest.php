<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Bus::fake();

        $this->tenant = Tenant::create([
            'name'                => 'Test Company',
            'email'               => 'test@company.com',
            'plan'                => TenantPlan::Starter->value,
            'subscription_status' => TenantStatus::Active->value,
            'rbi_bank_rate'       => 6.75,
            'is_active'           => true,
        ]);

        $this->user = User::create([
            'name'              => 'Test User',
            'email'             => 'user@test.com',
            'password'          => bcrypt('password'),
            'tenant_id'         => $this->tenant->id,
            'role'              => UserRole::Owner->value,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /import
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function import_index_requires_authentication(): void
    {
        $response = $this->get('/import');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function import_index_returns_200_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get('/import');
        $response->assertStatus(200);
    }

    #[Test]
    public function import_index_returns_inertia_page(): void
    {
        $response = $this->actingAs($this->user)->get('/import');
        $response->assertInertia(fn ($page) => $page->component('Import/Index'));
    }

    #[Test]
    public function import_index_includes_batches_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/import');
        $response->assertInertia(fn ($page) => $page->has('batches'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /import — validation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post('/import', []);
        $response->assertRedirect('/login');
    }

    #[Test]
    public function store_requires_file(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/import', ['source' => 'csv']);

        $response->assertSessionHasErrors('file');
    }

    #[Test]
    public function store_requires_source(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 10, 'text/csv');

        $response = $this->actingAs($this->user)
            ->post('/import', ['file' => $file]);

        $response->assertSessionHasErrors('source');
    }

    #[Test]
    public function store_rejects_invalid_source_type(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 10, 'text/csv');

        $response = $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'invalid_source']);

        $response->assertSessionHasErrors('source');
    }

    #[Test]
    public function store_rejects_file_exceeding_10mb(): void
    {
        $file = UploadedFile::fake()->create('huge.csv', 11 * 1024, 'text/csv');

        $response = $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $response->assertSessionHasErrors('file');
    }

    #[Test]
    public function store_rejects_disallowed_file_type(): void
    {
        $file = UploadedFile::fake()->create('invoices.pdf', 10, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $response->assertSessionHasErrors('file');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /import — success path
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function store_creates_import_batch_for_csv(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 5, 'text/csv');

        $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $this->assertDatabaseHas('import_batches', [
            'tenant_id'  => $this->tenant->id,
            'source'     => ImportSource::Csv->value,
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function store_creates_import_batch_for_tally_xml(): void
    {
        $file = UploadedFile::fake()->create('export.xml', 5, 'text/xml');

        $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'tally_xml']);

        $this->assertDatabaseHas('import_batches', [
            'tenant_id' => $this->tenant->id,
            'source'    => ImportSource::TallyXml->value,
        ]);
    }

    #[Test]
    public function store_stores_original_filename(): void
    {
        $file = UploadedFile::fake()->create('my-invoices-jan.csv', 5, 'text/csv');

        $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $this->assertDatabaseHas('import_batches', [
            'original_filename' => 'my-invoices-jan.csv',
        ]);
    }

    #[Test]
    public function store_dispatches_process_import_batch_job(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 5, 'text/csv');

        $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        Bus::assertDispatched(ProcessImportBatch::class);
    }

    #[Test]
    public function store_redirects_to_batch_show_page(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 5, 'text/csv');

        $response = $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $batch = ImportBatch::latest()->first();
        $response->assertRedirect("/import/{$batch->id}");
    }

    #[Test]
    public function store_sets_batch_status_pending(): void
    {
        $file = UploadedFile::fake()->create('invoices.csv', 5, 'text/csv');

        $this->actingAs($this->user)
            ->post('/import', ['file' => $file, 'source' => 'csv']);

        $batch = ImportBatch::latest()->first();
        $this->assertSame(ImportStatus::Pending->value, $batch->status->value);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /import/{batch}
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function show_returns_200_for_own_batch(): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'tenant_id'   => $this->tenant->id,
            'source'      => ImportSource::Csv->value,
            'status'      => ImportStatus::Completed->value,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/import/{$batch->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Import/Show'));
    }

    #[Test]
    public function show_includes_batch_and_errors_props(): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'tenant_id'  => $this->tenant->id,
            'source'     => ImportSource::Csv->value,
            'status'     => ImportStatus::Completed->value,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/import/{$batch->id}");

        $response->assertInertia(fn ($page) => $page
            ->has('batch')
            ->has('errors')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /import/sample/{type}
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function download_sample_returns_csv_file(): void
    {
        // Use real storage for this test (sample file must exist)
        Storage::fake('local'); // reset faked storage
        $this->skipIfSampleMissing('sample-import.csv');

        $response = $this->actingAs($this->user)
            ->get('/import/sample/csv');

        $response->assertStatus(200);
    }

    #[Test]
    public function download_sample_returns_404_for_invalid_type(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/import/sample/unknown');

        $response->assertStatus(404);
    }

    private function skipIfSampleMissing(string $filename): void
    {
        if (! file_exists(storage_path("app/samples/{$filename}"))) {
            $this->markTestSkipped("Sample file {$filename} not present in storage/app/samples/");
        }
    }
}
