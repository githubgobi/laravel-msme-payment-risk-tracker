<?php

namespace Database\Factories;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        $total     = fake()->numberBetween(10, 100);
        $failed    = fake()->numberBetween(0, (int) ($total * 0.05));
        $skipped   = fake()->numberBetween(0, (int) ($total * 0.1));
        $processed = $total;

        return [
            'tenant_id'         => Tenant::factory(),
            'source'            => ImportSource::Csv->value,
            'original_filename' => fake()->bothify('invoices-????-##.csv'),
            'stored_path'       => null,
            'total_rows'        => $total,
            'processed_rows'    => $processed,
            'skipped_rows'      => $skipped,
            'failed_rows'       => $failed,
            'status'            => ImportStatus::Completed->value,
            'error_log'         => null,
            'started_at'        => now()->subMinutes(5),
            'completed_at'      => now()->subMinutes(1),
            'created_by'        => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status'       => ImportStatus::Pending->value,
            'started_at'   => null,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'       => ImportStatus::Failed->value,
            'completed_at' => now(),
        ]);
    }

    public function tallyXml(): static
    {
        return $this->state([
            'source'            => ImportSource::TallyXml->value,
            'original_filename' => fake()->bothify('tally-export-####.xml'),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
