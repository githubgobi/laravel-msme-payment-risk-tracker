<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'text',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding'   => 'array',
            'chunk_index' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }
}
