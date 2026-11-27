<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE organization_user MODIFY COLUMN role VARCHAR(64) NOT NULL DEFAULT 'sales_rep'");
            DB::statement("ALTER TABLE organization_invitations MODIFY COLUMN role VARCHAR(64) NOT NULL DEFAULT 'sales_rep'");
        } else {
            Schema::table('organization_user', function (Blueprint $table): void {
                $table->string('role', 64)->default('sales_rep')->change();
            });
            Schema::table('organization_invitations', function (Blueprint $table): void {
                $table->string('role', 64)->default('sales_rep')->change();
            });
        }

        if (! Schema::hasColumn('organization_user', 'invited_by_user_id')) {
            Schema::table('organization_user', function (Blueprint $table): void {
                $table->foreignId('invited_by_user_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('rbac_menus')) {
            Schema::create('rbac_menus', function (Blueprint $table): void {
                $table->id();
                $table->string('label', 100);
                $table->string('route_name', 120);
                $table->string('route_pattern', 120)->default('*');
                $table->string('icon', 64)->default('circle');
                $table->string('permission_slug', 100)->nullable()->index();
                $table->string('section', 32)->default('main');
                $table->boolean('superadmin_only')->default(false);
                $table->boolean('requires_org')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_menus');

        if (Schema::hasColumn('organization_user', 'invited_by_user_id')) {
            Schema::table('organization_user', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('invited_by_user_id');
            });
        }
    }
};
