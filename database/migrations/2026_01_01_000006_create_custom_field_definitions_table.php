<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->enum('entity_type', ['lead', 'contact', 'account', 'deal']);
            $table->string('field_key', 64);
            $table->string('label', 100);
            $table->enum('data_type', ['text', 'number', 'date', 'boolean', 'select', 'multiselect', 'textarea']);
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'entity_type', 'field_key'], 'uk_org_entity_key');
            $table->index(['organization_id', 'entity_type', 'order_index'], 'idx_cfd_org_entity_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
