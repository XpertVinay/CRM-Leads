<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('activitable_type', 191);
            $table->unsignedBigInteger('activitable_id');
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id']);
            $table->index(['activitable_type', 'activitable_id'], 'idx_activity_morphs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
