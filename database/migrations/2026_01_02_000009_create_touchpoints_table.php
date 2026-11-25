<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('touchpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('tracking_link_id')
                ->nullable()
                ->constrained('tracking_links')
                ->cascadeOnDelete();
            $table->foreignId('link_click_id')
                ->nullable()
                ->constrained('link_clicks')
                ->nullOnDelete();
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table->foreignId('deal_id')
                ->nullable()
                ->constrained('deals')
                ->nullOnDelete();
            $table->enum('event_milestone', ['click', 'lead_captured', 'conversation_qualified', 'deal_won']);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'event_milestone'], 'idx_tp_org_milestone');
            $table->index(['lead_id'], 'idx_tp_lead');
            $table->index(['deal_id'], 'idx_tp_deal');
            $table->index(['contact_id'], 'idx_tp_contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('touchpoints');
    }
};
