<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('name', 191);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_default'], 'idx_pipe_org_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
