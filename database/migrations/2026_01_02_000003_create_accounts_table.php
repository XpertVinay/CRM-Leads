<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('domain', 191)->nullable();
            $table->string('industry', 100)->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name'], 'idx_account_org_name');
            $table->index(['organization_id', 'domain'], 'idx_account_org_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
