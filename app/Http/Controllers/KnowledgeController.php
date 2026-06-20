<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeSourceType;
use App\Services\Knowledge\KnowledgeRepository;
use App\Services\Knowledge\VendorIngester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function __construct(
        private readonly KnowledgeRepository $repository,
        private readonly VendorIngester      $ingester,
    ) {}

    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        return Inertia::render('Knowledge/Index', [
            'stats' => $this->repository->stats($tenantId),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(
            $this->repository->stats(auth()->user()->tenant_id)
        );
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query'       => ['required', 'string', 'min:1', 'max:500'],
            'top_k'       => ['integer', 'min:1', 'max:20'],
            'source_type' => ['nullable', 'string', 'in:vendor,manual'],
        ]);

        $sourceType = isset($data['source_type'])
            ? KnowledgeSourceType::from($data['source_type'])
            : null;

        $results = $this->repository->search(
            tenantId:   auth()->user()->tenant_id,
            query:      $data['query'],
            topK:       $data['top_k'] ?? 5,
            sourceType: $sourceType,
        );

        return response()->json([
            'query'   => $data['query'],
            'results' => $results,
        ]);
    }

    public function ingestVendors(): JsonResponse
    {
        abort_unless(config('llm.enabled'), 404);

        $result = $this->ingester->ingestAll(
            tenantId: auth()->user()->tenant_id,
            userId:   auth()->id(),
        );

        return response()->json($result);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->repository->deleteDocument(auth()->user()->tenant_id, $id);

        abort_unless($deleted, 404, 'Knowledge document not found.');

        return response()->json(['deleted' => true, 'id' => $id]);
    }
}
