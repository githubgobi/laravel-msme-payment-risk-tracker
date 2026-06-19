<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');

            // JSON array of alternate names/spellings for LLM fuzzy matching
            $table->json('aliases')->nullable();

            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('udyam_number', 20)->nullable();
            $table->timestamp('udyam_verified_at')->nullable();

            // Snapshot of the MSME category (micro/small/medium/large/unclassified)
            $table->string('category', 20)->default('unclassified');
            $table->string('verification_source', 10)->default('manual');

            $table->string('state', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Core query indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category']);
            $table->index('udyam_number');
            $table->index('gstin');

            // Prevent duplicate vendors per tenant by GSTIN
            $table->unique(['tenant_id', 'gstin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
