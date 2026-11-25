<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->enum('type', ['B2B', 'B2C'])->default('B2C');
            $table->enum('status', ['new', 'contacted', 'qualified', 'unqualified', 'converted'])->default('new');
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('company_name', 191)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('source', 100)->default('direct');
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('converted_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table->foreignId('converted_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignId('converted_deal_id')
                ->nullable()
                ->constrained('deals')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'idx_lead_org_status');
            $table->index(['organization_id', 'type'], 'idx_lead_org_type');
            $table->index(['organization_id', 'assigned_user_id'], 'idx_lead_assigned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
