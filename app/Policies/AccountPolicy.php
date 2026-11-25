<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class AccountPolicy
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

    public function view(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $account->organization_id !== (int) $tenant->id) {
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

    public function update(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $account->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
            TenantRole::SalesRep->value,
        ]);
    }

    public function delete(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $account->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }
}
