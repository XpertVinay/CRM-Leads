<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('tracking_link_id')
                ->constrained('tracking_links')
                ->cascadeOnDelete();
            $table->char('click_token', 36)->unique();
            $table->char('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->string('referer', 255)->nullable();
            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['tracking_link_id', 'clicked_at'], 'idx_lc_link_clicked');
            $table->index(['organization_id', 'clicked_at'], 'idx_lc_org_clicked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
    }
};
