<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('waba_id', 100);
            $table->string('phone_number_id', 100)->unique();
            $table->string('display_phone_number', 50);
            $table->string('verified_name', 191)->nullable();
            $table->string('quality_rating', 50)->nullable();
            $table->text('access_token_encrypted');
            $table->string('webhook_verify_token', 100)->nullable();
            $table->boolean('is_coexistence_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status', 30)->default('connected');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
