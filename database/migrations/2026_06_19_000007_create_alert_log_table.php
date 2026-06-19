<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('alert_type', 30);
            $table->string('status', 20)->default('pending');

            $table->json('payload')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('failed_reason')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('purchase_invoices')->nullOnDelete();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'alert_type', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_log');
    }
};
