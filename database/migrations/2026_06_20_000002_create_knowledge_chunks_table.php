<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedSmallInteger('chunk_index');
            $table->text('text');

            // Float array stored as JSON. Dimension depends on model:
            // nomic-embed-text → 768, llama3.2 → 4096, hash fallback → 256.
            // Dimension is NOT enforced here — KnowledgeRepository handles mismatches.
            $table->json('embedding');

            $table->timestamps();

            $table->foreign('document_id')
                ->references('id')
                ->on('knowledge_documents')
                ->cascadeOnDelete();

            $table->index(['document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
