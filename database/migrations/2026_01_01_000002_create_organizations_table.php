<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('registration_identifier', 100)->nullable()->unique();
            $table->string('primary_contact_email', 191)->nullable()->unique();
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->string('timezone', 50)->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
