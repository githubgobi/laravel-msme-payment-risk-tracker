<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max

    public int $tries = 1; // import is not idempotent — never auto-retry

    public function __construct(
        public readonly ImportBatch $batch,
    ) {}

    public function handle(ImportPipeline $pipeline): void
    {
        $pipeline->process($this->batch);
    }

    public function failed(Throwable $exception): void
    {
        // The pipeline marks the batch as failed internally.
        // This fallback handles unexpected PHP-level errors.
        $this->batch->withoutGlobalScopes()->where('id', $this->batch->id)->update([
            'status'    => \App\Enums\ImportStatus::Failed->value,
            'error_log' => json_encode([
                ['status' => 'failed', 'row' => 0, 'invoice_number' => '', 'message' => $exception->getMessage()],
            ]),
            'completed_at' => now(),
        ]);
    }
}
