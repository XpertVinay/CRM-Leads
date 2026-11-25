<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->enum('preferred_channel', ['whatsapp', 'email', 'phone'])->default('email');
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'email'], 'idx_contact_org_email');
            $table->index(['organization_id', 'phone'], 'idx_contact_org_phone');
            $table->index(['organization_id', 'account_id'], 'idx_contact_org_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
