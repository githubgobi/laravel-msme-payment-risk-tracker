<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->string('role', 20)->default('finance')->after('name');
            $table->string('phone', 15)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropColumn(['tenant_id', 'role', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
