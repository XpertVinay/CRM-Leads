<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('whatsapp_phone', 32)->nullable();
            $table->text('whatsapp_default_message')->nullable();
            $table->string('duplicate_lead_strategy', 32)->default('merge_history');
            $table->boolean('capture_ip_address')->default(false);
            $table->string('lead_reference_prefix', 16)->default('LD');
            $table->boolean('require_email')->default(false);
            $table->boolean('require_company')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
