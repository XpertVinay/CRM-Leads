<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('status', 50)->default('new')->change();
            $table->string('lead_reference', 32)->nullable()->after('organization_id');
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('lead_reference')
                ->constrained('campaigns')
                ->nullOnDelete();
            $table->foreignId('tracking_link_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained('tracking_links')
                ->nullOnDelete();
            $table->string('priority', 20)->default('medium')->after('status');
            $table->text('requirement')->nullable()->after('location');
            $table->unsignedInteger('duplicate_count')->default(0)->after('source');
            $table->timestamp('last_inquiry_at')->nullable()->after('duplicate_count');

            $table->unique(['organization_id', 'lead_reference'], 'uq_lead_org_ref');
            $table->index(['organization_id', 'phone'], 'idx_lead_org_phone');
            $table->index(['organization_id', 'campaign_id'], 'idx_lead_org_campaign');
            $table->index(['organization_id', 'priority'], 'idx_lead_org_priority');
        });

        Schema::table('touchpoints', function (Blueprint $table): void {
            $table->string('event_milestone', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('uq_lead_org_ref');
            $table->dropIndex('idx_lead_org_phone');
            $table->dropIndex('idx_lead_org_campaign');
            $table->dropIndex('idx_lead_org_priority');
            $table->dropConstrainedForeignId('tracking_link_id');
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn(['lead_reference', 'priority', 'requirement', 'duplicate_count', 'last_inquiry_at']);
        });
    }
};
