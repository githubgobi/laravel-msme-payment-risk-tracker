<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceType;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class KnowledgeRepository
{
    // Prevents OOM on large knowledge bases; replace with pgvector/FAISS at scale
    private const SEARCH_CHUNK_CAP = 2000;

    public function __construct(
        private readonly DocumentChunker  $chunker,
        private readonly EmbeddingService $embedder,
        private readonly CosineSimilarity $similarity,
    ) {}

    /**
     * Chunk, embed, and persist a document. Returns the saved KnowledgeDocument.
     */
    public function addDocument(
        int                $tenantId,
        string             $title,
        string             $content,
        KnowledgeSourceType $sourceType  = KnowledgeSourceType::Manual,
        ?int               $sourceId    = null,
        ?int               $createdBy   = null,
    ): KnowledgeDocument {
        return DB::transaction(function () use ($tenantId, $title, $content, $sourceType, $sourceId, $createdBy) {
            $doc = KnowledgeDocument::withoutGlobalScopes()->create([
                'tenant_id'   => $tenantId,
                'title'       => $title,
                'source_type' => $sourceType,
                'content'     => $content,
                'source_id'   => $sourceId,
                'created_by'  => $createdBy,
                'chunk_count' => 0,
            ]);

            $texts  = $this->chunker->chunk($content);
            $model  = HashEmbedder::MODEL_NAME;

            foreach ($texts as $index => $chunkText) {
                [$vec, $model] = $this->embedder->embed($chunkText);

                KnowledgeChunk::create([
                    'document_id' => $doc->id,
                    'chunk_index' => $index,
                    'text'        => $chunkText,
                    'embedding'   => $vec,
                ]);
            }

            $doc->update([
                'chunk_count'     => count($texts),
                'embedding_model' => $model,
            ]);

            Log::info('Knowledge document indexed', [
                'doc_id'  => $doc->id,
                'title'   => $title,
                'chunks'  => count($texts),
                'model'   => $model,
            ]);

            return $doc->fresh();
        });
    }

    /**
     * Re-index a document (delete existing, create fresh).
     * Used when the source record (e.g. vendor classification) changes.
     */
    public function reindexDocument(
        int                $tenantId,
        string             $title,
        string             $content,
        KnowledgeSourceType $sourceType,
        int                $sourceId,
        ?int               $createdBy = null,
    ): KnowledgeDocument {
        KnowledgeDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();

        return $this->addDocument($tenantId, $title, $content, $sourceType, $sourceId, $createdBy);
    }

    public function deleteDocument(int $tenantId, int $docId): bool
    {
        return (bool) KnowledgeDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $docId)
            ->delete();
    }

    /** @return KnowledgeDocument[] */
    public function listDocuments(int $tenantId, int $skip = 0, int $limit = 50): array
    {
        return KnowledgeDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->skip($skip)
            ->take($limit)
            ->get()
            ->all();
    }

    public function getDocument(int $tenantId, int $docId): ?KnowledgeDocument
    {
        return KnowledgeDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $docId)
            ->first();
    }

    /**
     * Semantic search over all knowledge chunks for a tenant.
     * Returns at most $topK results ranked by cosine similarity descending.
     *
     * Each result array contains: chunk_id, document_id, document_title,
     * source_type, chunk_index, text, score.
     *
     * @return array<int, array{chunk_id:int, document_id:int, document_title:string, source_type:string, chunk_index:int, text:string, score:float}>
     */
    public function search(
        int                  $tenantId,
        string               $query,
        int                  $topK       = 5,
        ?KnowledgeSourceType $sourceType = null,
    ): array {
        if (trim($query) === '') {
            return [];
        }

        [$queryVec, $queryModel] = $this->embedder->embed($query);

        $chunkQuery = KnowledgeChunk::query()
            ->join('knowledge_documents', 'knowledge_chunks.document_id', '=', 'knowledge_documents.id')
            ->where('knowledge_documents.tenant_id', $tenantId)
            ->select('knowledge_chunks.*', 'knowledge_documents.title as doc_title', 'knowledge_documents.source_type as doc_source_type')
            ->limit(self::SEARCH_CHUNK_CAP);

        if ($sourceType !== null) {
            $chunkQuery->where('knowledge_documents.source_type', $sourceType);
        }

        $chunks  = $chunkQuery->get();
        $results = [];

        foreach ($chunks as $chunk) {
            $stored = $chunk->embedding;

            if (empty($stored)) {
                continue;
            }

            // Handle dimension mismatch — re-embed query to match stored dimension
            if (count($stored) !== count($queryVec)) {
                $adjustedQuery = $this->embedder->embedToMatchDimension($query, count($stored));
            } else {
                $adjustedQuery = $queryVec;
            }

            $score = $this->similarity->compute($adjustedQuery, $stored);

            $results[] = [
                'chunk_id'       => $chunk->id,
                'document_id'    => $chunk->document_id,
                'document_title' => $chunk->doc_title,
                'source_type'    => $chunk->doc_source_type,
                'chunk_index'    => $chunk->chunk_index,
                'text'           => $chunk->text,
                'score'          => round($score, 4),
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    public function stats(int $tenantId): array
    {
        $docCount   = KnowledgeDocument::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
        $chunkCount = KnowledgeChunk::query()
            ->join('knowledge_documents', 'knowledge_chunks.document_id', '=', 'knowledge_documents.id')
            ->where('knowledge_documents.tenant_id', $tenantId)
            ->count();

        $byType = KnowledgeDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->selectRaw('source_type, count(*) as total')
            ->groupBy('source_type')
            ->pluck('total', 'source_type')
            ->toArray();

        return [
            'total_documents' => $docCount,
            'total_chunks'    => $chunkCount,
            'by_source_type'  => $byType,
        ];
    }
}
