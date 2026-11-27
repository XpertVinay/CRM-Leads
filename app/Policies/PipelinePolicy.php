<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class PipelinePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'pipelines.view');
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $pipeline->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'pipelines.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'pipelines.create');
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $pipeline->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'pipelines.update');
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $pipeline->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'pipelines.delete');
    }
}
