<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class DealPolicy
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

    public function view(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $deal->organization_id !== (int) $tenant->id) {
            return false;
        }

        return in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
            TenantRole::SalesRep->value,
        ]);
    }

    public function update(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $deal->organization_id !== (int) $tenant->id) {
            return false;
        }

        $role = $user->roleIn($tenant);
        if (in_array($role, [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value], true)) {
            return true;
        }

        if ($role === TenantRole::SalesRep->value) {
            return $deal->assigned_user_id === null || (int) $deal->assigned_user_id === (int) $user->id;
        }

        return false;
    }

    public function delete(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $deal->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }

    public function transitionStage(User $user, Deal $deal): bool
    {
        return $this->update($user, $deal);
    }
}
