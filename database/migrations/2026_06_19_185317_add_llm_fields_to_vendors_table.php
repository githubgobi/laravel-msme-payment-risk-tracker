<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Audit trail for AI-generated classifications and fuzzy matches.
            // DECIMAL(4,3) stores 0.000–1.000 with 3 decimal precision.
            $table->decimal('llm_confidence', 4, 3)->nullable()->after('notes');
            $table->text('llm_reasoning')->nullable()->after('llm_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['llm_confidence', 'llm_reasoning']);
        });
    }
};
