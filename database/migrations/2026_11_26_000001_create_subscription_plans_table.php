<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_paise');
            $table->char('currency', 3)->default('INR');
            $table->string('billing_interval', 20)->default('month');
            $table->unsignedInteger('lead_limit')->default(25);
            $table->unsignedInteger('user_limit')->default(2);
            $table->unsignedBigInteger('extra_user_price_paise')->default(0);
            $table->json('features')->nullable();
            $table->string('razorpay_plan_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'idx_sub_plans_active_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
