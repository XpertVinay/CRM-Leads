<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')
                ->constrained('whatsapp_accounts')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('language', 20)->default('en_US');
            $table->string('category', 30)->default('utility'); // marketing, utility, authentication
            $table->string('header_type', 20)->default('none');
            $table->text('body_text');
            $table->json('parameter_placeholders')->nullable();
            $table->string('meta_status', 30)->default('PENDING'); // APPROVED, REJECTED, PENDING
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'name', 'language'], 'uk_wt_acc_name_lang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
