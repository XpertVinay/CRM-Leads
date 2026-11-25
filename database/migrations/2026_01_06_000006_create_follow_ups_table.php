<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('type', 32)->default('call');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->string('outcome', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_at'], 'idx_fu_org_status_due');
            $table->index(['organization_id', 'assigned_user_id', 'due_at'], 'idx_fu_org_user_due');
            $table->index(['organization_id', 'lead_id'], 'idx_fu_org_lead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
