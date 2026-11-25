<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('type', 64)->default('whatsapp');
            $table->string('platform', 64)->default('other');
            $table->decimal('budget', 15, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'idx_campaign_org_status');
            $table->index(['organization_id', 'platform'], 'idx_campaign_org_platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
