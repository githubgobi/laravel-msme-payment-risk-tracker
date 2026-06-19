<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->softDeletes()->after('completed_at');
        });

        Schema::table('alert_log', function (Blueprint $table) {
            $table->softDeletes()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('alert_log', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
