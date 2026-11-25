<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_links', function (Blueprint $table): void {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('campaigns')
                ->nullOnDelete();
            $table->string('slug', 100)->nullable()->unique()->after('code');
            $table->string('source', 100)->nullable()->after('channel');
            $table->string('medium', 100)->nullable()->after('source');
            $table->string('whatsapp_number', 32)->nullable()->after('ad_spend');
            $table->text('whatsapp_message')->nullable()->after('whatsapp_number');
            $table->timestamp('expires_at')->nullable()->after('is_active');

            $table->index(['organization_id', 'campaign_id'], 'idx_tl_org_campaign');
        });

        Schema::table('link_clicks', function (Blueprint $table): void {
            $table->char('visitor_hash', 64)->nullable()->after('ip_hash');
            $table->string('device', 50)->nullable()->after('user_agent');
            $table->string('browser', 50)->nullable()->after('device');
            $table->string('landing_page', 255)->nullable()->after('referer');
            $table->string('ip_address', 45)->nullable()->after('ip_hash');

            $table->index(['tracking_link_id', 'visitor_hash'], 'idx_lc_link_visitor');
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table): void {
            $table->dropIndex('idx_lc_link_visitor');
            $table->dropColumn(['visitor_hash', 'device', 'browser', 'landing_page', 'ip_address']);
        });

        Schema::table('tracking_links', function (Blueprint $table): void {
            $table->dropIndex('idx_tl_org_campaign');
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn(['slug', 'source', 'medium', 'whatsapp_number', 'whatsapp_message', 'expires_at']);
        });
    }
};
