<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('role', ['org_admin', 'sales_manager', 'sales_rep', 'read_only'])
                ->default('sales_rep');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'uk_org_user');
            $table->index(['user_id', 'organization_id'], 'idx_ou_user_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
