<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('rbac_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 120);
            $table->string('module', 64)->index();
            $table->boolean('is_menu_item')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rbac_role_permission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')
                ->constrained('rbac_roles')
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('rbac_permissions')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'uq_rbac_role_perm');
        });

        Schema::create('rbac_user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('rbac_permissions')
                ->cascadeOnDelete();
            $table->boolean('allowed');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'permission_id'], 'uq_rbac_org_user_perm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_user_permissions');
        Schema::dropIfExists('rbac_role_permission');
        Schema::dropIfExists('rbac_permissions');
        Schema::dropIfExists('rbac_roles');
    }
};
