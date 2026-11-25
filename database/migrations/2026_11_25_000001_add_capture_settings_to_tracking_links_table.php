<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_links', function (Blueprint $table): void {
            $table->json('capture_custom_field_ids')->nullable()->after('whatsapp_message');
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('capture_custom_field_ids')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('auto_assign_round_robin')->default(false)->after('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['capture_custom_field_ids', 'auto_assign_round_robin']);
        });
    }
};
