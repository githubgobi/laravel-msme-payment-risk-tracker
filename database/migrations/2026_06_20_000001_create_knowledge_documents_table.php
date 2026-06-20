<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('source_type', 20)->default('manual');
            $table->text('content');
            $table->unsignedSmallInteger('chunk_count')->default(0);
            $table->string('embedding_model', 60)->default('hash');

            // When source_type = 'vendor', source_id = vendors.id.
            // Null for manually added documents.
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'source_type']);

            // Prevent double-indexing the same vendor/invoice record
            $table->unique(['tenant_id', 'source_type', 'source_id'], 'knowledge_docs_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
