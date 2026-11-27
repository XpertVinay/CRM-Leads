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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'accounts.view');
    }

    public function view(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $account->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'accounts.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'accounts.create');
    }

    public function update(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $account->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'accounts.update');
    }

    public function delete(User $user, Account $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $account->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'accounts.delete');
    }
}
