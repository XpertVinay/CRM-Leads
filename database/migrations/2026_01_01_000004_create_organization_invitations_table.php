<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('email', 191);
            $table->enum('role', ['org_admin', 'sales_manager', 'sales_rep', 'read_only'])
                ->default('sales_rep');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'email'], 'idx_oi_org_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
