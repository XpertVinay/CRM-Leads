<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('pipeline_id')
                ->constrained('pipelines')
                ->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')
                ->constrained('pipeline_stages')
                ->restrictOnDelete();
            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->restrictOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title', 191);
            $table->decimal('amount', 15, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->date('expected_close_date')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason', 255)->nullable();
            $table->boolean('is_stale')->default(false);
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'pipeline_stage_id'], 'idx_deal_org_stage');
            $table->index(['organization_id', 'assigned_user_id'], 'idx_deal_org_owner');
            $table->index(['organization_id', 'closed_at'], 'idx_deal_org_closed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
