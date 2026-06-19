<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('import_batch_id')->nullable();

            $table->string('invoice_number', 100);
            $table->date('invoice_date');

            // Monetary values stored as DECIMAL(15,2) — avoids float precision issues
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            // MySQL: stored computed column; SQLite (used in tests) falls back to regular column
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->decimal('balance', 15, 2)->storedAs('amount - paid_amount');
            } else {
                $table->decimal('balance', 15, 2)->default(0.00);
            }

            $table->string('currency', 3)->default('INR');

            // Section 43B(h) deadline logic
            $table->boolean('agreement_exists')->default(false);
            $table->date('agreement_date')->nullable();
            $table->date('effective_deadline');

            // Snapshot of vendor MSME category at time of invoice — historical accuracy
            $table->string('vendor_category_snapshot', 20)->default('unclassified');

            // Financial year string e.g. "2025-26" — computed from invoice_date on creation
            $table->string('financial_year', 7);

            // Computed by MsmeDeadlineEngine and stored for performance
            $table->decimal('disallowance_amount', 15, 2)->default(0.00);
            $table->decimal('interest_amount', 15, 2)->default(0.00);
            $table->timestamp('last_computed_at')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('narration')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Primary dashboard query: at-risk invoices by deadline
            $table->index(['tenant_id', 'effective_deadline', 'status']);
            // Dashboard KPI cards
            $table->index(['tenant_id', 'status']);
            // Year-end tax exposure report
            $table->index(['tenant_id', 'financial_year', 'status']);
            // Vendor drill-down
            $table->index(['tenant_id', 'vendor_id', 'status']);
            // Import batch traceability
            $table->index('import_batch_id');

            // Prevent duplicate invoice imports
            $table->unique(['tenant_id', 'invoice_number', 'vendor_id'], 'uq_invoice_per_vendor_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
