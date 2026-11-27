<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class CustomFieldDefinitionPolicy
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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'custom_fields.view');
    }

    public function view(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $definition->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'custom_fields.view');
    }

    public function manageSettings(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'custom_fields.manage');
    }

    public function create(User $user): bool
    {
        return $this->manageSettings($user);
    }

    public function update(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $definition->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'custom_fields.manage');
    }

    public function delete(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $definition->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'custom_fields.manage');
    }
}
