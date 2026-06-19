<?php

namespace App\Http\Controllers;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $batches = ImportBatch::latest()
            ->paginate(20)
            ->through(fn ($batch) => [
                'id'               => $batch->id,
                'source'           => $batch->source->value,
                'source_label'     => $batch->source->label(),
                'original_filename'=> $batch->original_filename,
                'total_rows'       => $batch->total_rows,
                'processed_rows'   => $batch->processed_rows,
                'skipped_rows'     => $batch->skipped_rows,
                'failed_rows'      => $batch->failed_rows,
                'success_rate'     => $batch->successRate(),
                'status'           => $batch->status->value,
                'status_label'     => $batch->status->label(),
                'created_at'       => $batch->created_at?->diffForHumans(),
                'completed_at'     => $batch->completed_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Import/Index', [
            'batches' => $batches,
        ]);
    }

    public function store(StoreImportRequest $request): HttpResponse
    {
        $user   = $request->user();
        $source = ImportSource::from($request->input('source'));

        // Store the file under imports/{tenant_id}/
        $path = $request->file('file')->store(
            "imports/{$user->tenant_id}",
            'local',
        );

        // Create the ImportBatch record
        $batch = ImportBatch::withoutGlobalScopes()->create([
            'tenant_id'         => $user->tenant_id,
            'source'            => $source->value,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'stored_path'       => $path,
            'status'            => ImportStatus::Pending->value,
            'created_by'        => $user->id,
        ]);

        // Dispatch import job — synchronous if QUEUE_CONNECTION=sync (local dev)
        ProcessImportBatch::dispatch($batch);

        return redirect()
            ->route('import.show', $batch)
            ->with('success', 'Import started. Results will appear below.');
    }

    public function show(ImportBatch $batch): Response
    {
        $errorLog = $batch->error_log ?? [];

        return Inertia::render('Import/Show', [
            'batch' => [
                'id'                => $batch->id,
                'source'            => $batch->source->value,
                'source_label'      => $batch->source->label(),
                'original_filename' => $batch->original_filename,
                'total_rows'        => $batch->total_rows,
                'processed_rows'    => $batch->processed_rows,
                'skipped_rows'      => $batch->skipped_rows,
                'failed_rows'       => $batch->failed_rows,
                'success_rate'      => $batch->successRate(),
                'status'            => $batch->status->value,
                'status_label'      => $batch->status->label(),
                'started_at'        => $batch->started_at?->format('d M Y H:i:s'),
                'completed_at'      => $batch->completed_at?->format('d M Y H:i:s'),
                'created_at'        => $batch->created_at?->format('d M Y H:i'),
            ],
            'errors' => array_slice($errorLog, 0, 500),
        ]);
    }

    public function downloadSample(string $type): HttpResponse
    {
        $files = [
            'csv'       => 'samples/sample-import.csv',
            'tally_xml' => 'samples/sample-tally.xml',
        ];

        if (! isset($files[$type])) {
            abort(404);
        }

        $path = storage_path('app/' . $files[$type]);

        if (! file_exists($path)) {
            abort(404, 'Sample file not found');
        }

        return response()->download($path);
    }
}
