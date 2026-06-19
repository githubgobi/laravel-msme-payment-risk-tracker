<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Razorpay customer ID — created when tenant initiates first payment
            $table->string('razorpay_customer_id', 50)->nullable()->after('settings');

            // Active subscription ID; null = no active subscription (trial or expired)
            $table->string('razorpay_subscription_id', 50)->nullable()->after('razorpay_customer_id');

            // Razorpay plan ID for the current tier (matches plan_id in services.php)
            $table->string('razorpay_plan_id', 50)->nullable()->after('razorpay_subscription_id');

            // Set when payment fails (subscription.halted webhook); account stays accessible
            // until this timestamp, then EnsureActiveTenant suspends the tenant
            $table->timestamp('grace_period_ends_at')->nullable()->after('razorpay_plan_id');

            $table->index('razorpay_subscription_id');
            $table->index('grace_period_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['razorpay_subscription_id']);
            $table->dropIndex(['grace_period_ends_at']);
            $table->dropColumn([
                'razorpay_customer_id',
                'razorpay_subscription_id',
                'razorpay_plan_id',
                'grace_period_ends_at',
            ]);
        });
    }
};
