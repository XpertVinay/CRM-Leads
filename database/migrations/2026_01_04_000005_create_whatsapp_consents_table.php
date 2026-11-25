<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();
            $table->string('phone', 50);
            $table->boolean('is_opted_in')->default(true);
            $table->string('consent_source', 100);
            $table->timestamp('consent_timestamp')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'phone'], 'idx_wcon_org_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_consents');
    }
};
