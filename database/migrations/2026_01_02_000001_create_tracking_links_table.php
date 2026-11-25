<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 191);
            $table->text('destination_url');
            $table->enum('channel', ['ad', 'whatsapp', 'email', 'social', 'qr', 'organic']);
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->decimal('ad_spend', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'code'], 'idx_tl_org_code');
            $table->index(['organization_id', 'channel'], 'idx_tl_org_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_links');
    }
};
