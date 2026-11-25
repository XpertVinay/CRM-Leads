<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pipeline_id')
                ->constrained('pipelines')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('order_index')->default(0);
            $table->unsignedTinyInteger('win_probability')->default(0);
            $table->enum('category', ['open', 'won', 'lost'])->default('open');
            $table->unsignedSmallInteger('stale_days')->nullable()->default(14);
            $table->timestamps();

            $table->index(['pipeline_id', 'order_index'], 'idx_stage_order');
            $table->index(['pipeline_id', 'category'], 'idx_stage_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
