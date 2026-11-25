<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable()->after('email');
            $table->boolean('is_superadmin')->default(false)->after('phone');
            $table->index('is_superadmin', 'idx_users_superadmin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_superadmin');
            $table->dropColumn(['phone', 'is_superadmin']);
        });
    }
};
