<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gstin', 15)->nullable()->unique();
            $table->string('pan', 10)->nullable();
            $table->string('business_type')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();

            $table->string('plan', 20)->default('starter');
            $table->string('subscription_status', 20)->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // RBI bank rate used for interest computation — admin-updatable
            $table->decimal('rbi_bank_rate', 5, 2)->default(6.75);

            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('subscription_status');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
