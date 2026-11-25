<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class FollowUpPolicy
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

        return $tenant !== null && in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $followUp->organization_id === (int) $tenant->id
            && in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
            TenantRole::SalesRep->value,
        ]);
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $followUp->organization_id !== (int) $tenant->id) {
            return false;
        }

        if ($user->hasRoleIn($tenant, [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value])) {
            return true;
        }

        return $user->hasRoleIn($tenant, TenantRole::SalesRep->value)
            && ((int) $followUp->assigned_user_id === (int) $user->id || (int) $followUp->created_by_user_id === (int) $user->id);
    }

    public function delete(User $user, FollowUp $followUp): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $followUp->organization_id === (int) $tenant->id
            && $user->hasRoleIn($tenant, [
                TenantRole::OrgAdmin->value,
                TenantRole::SalesManager->value,
            ]);
    }
}
