<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesTestCrmEntityTable
{
    protected function createTestCrmEntityTable(): void
    {
        if (! Schema::hasTable('test_crm_entities')) {
            Schema::create('test_crm_entities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')
                    ->constrained('organizations')
                    ->cascadeOnDelete();
                $table->string('entity_type', 32)->default('lead');
                $table->string('name', 191);
                $table->json('custom_fields')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function dropTestCrmEntityTable(): void
    {
        Schema::dropIfExists('test_crm_entities');
    }
}
