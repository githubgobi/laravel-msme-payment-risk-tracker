<?php

namespace App\Models;

use App\Enums\KnowledgeSourceType;
use App\Models\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'source_type',
        'content',
        'chunk_count',
        'embedding_model',
        'source_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => KnowledgeSourceType::class,
            'chunk_count' => 'integer',
            'source_id'   => 'integer',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }
}
