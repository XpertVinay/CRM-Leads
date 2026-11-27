<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans');
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();
            $table->enum('status', [
                'pending_payment',
                'authenticated',
                'active',
                'halted',
                'cancelled',
                'completed',
                'expired',
            ])->default('pending_payment');
            $table->string('razorpay_subscription_id', 100)->nullable()->unique();
            $table->string('razorpay_plan_id', 100)->nullable();
            $table->string('razorpay_customer_id', 100)->nullable();
            $table->unsignedBigInteger('base_amount_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->unsignedBigInteger('initial_charge_paise');
            $table->boolean('is_mandate_only')->default(false);
            $table->char('currency', 3)->default('INR');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'idx_subscriptions_org_status');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->cascadeOnDelete();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('discount_paise');
            $table->timestamp('redeemed_at')->useCurrent();

            $table->unique(['coupon_id', 'organization_id', 'subscription_id'], 'uq_coupon_org_sub');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('subscriptions');
    }
};
