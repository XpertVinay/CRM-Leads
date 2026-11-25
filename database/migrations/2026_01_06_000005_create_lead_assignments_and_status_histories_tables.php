<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['organization_id', 'lead_id'], 'idx_la_org_lead');
            $table->index(['organization_id', 'assigned_to_user_id'], 'idx_la_org_assignee');
        });

        Schema::create('lead_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['organization_id', 'lead_id'], 'idx_lsh_org_lead');
            $table->index(['organization_id', 'to_status'], 'idx_lsh_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_histories');
        Schema::dropIfExists('lead_assignments');
    }
};
