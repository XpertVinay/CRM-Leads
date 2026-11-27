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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'deals.view');
    }

    public function view(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $deal->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'deals.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'deals.create');
    }

    public function update(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $deal->organization_id !== (int) $tenant->id) {
            return false;
        }

        if (! $user->hasPermissionIn($tenant, 'deals.update')) {
            return false;
        }

        if ($user->roleIn($tenant) === TenantRole::SalesRep->value) {
            return $deal->assigned_user_id === null || (int) $deal->assigned_user_id === (int) $user->id;
        }

        return true;
    }

    public function delete(User $user, Deal $deal): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $deal->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'deals.delete');
    }

    public function transitionStage(User $user, Deal $deal): bool
    {
        return $this->update($user, $deal);
    }
}
