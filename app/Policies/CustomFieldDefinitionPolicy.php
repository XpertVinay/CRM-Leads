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

        if ($tenant === null) {
            return false;
        }

        return in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function view(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $definition->organization_id !== (int) $tenant->id) {
            return false;
        }

        return in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function manageSettings(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null) {
            return false;
        }

        return $user->roleIn($tenant) === TenantRole::OrgAdmin->value;
    }

    public function create(User $user): bool
    {
        return $this->manageSettings($user);
    }

    public function update(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $definition->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->roleIn($tenant) === TenantRole::OrgAdmin->value;
    }

    public function delete(User $user, CustomFieldDefinition $definition): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $definition->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->roleIn($tenant) === TenantRole::OrgAdmin->value;
    }
}
