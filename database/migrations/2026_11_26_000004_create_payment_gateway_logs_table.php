<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();
            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('direction', ['outbound_api', 'inbound_webhook', 'checkout_callback']);
            $table->string('event_or_endpoint', 191);
            $table->string('http_method', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('razorpay_subscription_id', 100)->nullable();
            $table->string('razorpay_payment_id', 100)->nullable();
            $table->string('razorpay_order_id', 100)->nullable();
            $table->string('razorpay_event_id', 100)->nullable()->unique();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->enum('status', ['success', 'failed', 'exception'])->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at'], 'idx_pg_logs_org_created');
            $table->index('razorpay_subscription_id', 'idx_pg_logs_rzp_sub');
            $table->index('razorpay_payment_id', 'idx_pg_logs_rzp_pay');
            $table->index(['direction', 'status'], 'idx_pg_logs_dir_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
