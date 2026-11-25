<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;

class OrganizationPolicy
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
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasRoleIn($organization, TenantRole::OrgAdmin->value);
    }

    public function manageTeam(User $user, Organization $organization): bool
    {
        return $user->hasRoleIn($organization, TenantRole::OrgAdmin->value);
    }

    public function manageTokens(User $user, Organization $organization): bool
    {
        return $user->hasRoleIn($organization, TenantRole::OrgAdmin->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasRoleIn($organization, TenantRole::OrgAdmin->value);
    }
}
