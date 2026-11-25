<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class LeadPolicy
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

    public function view(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
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

    public function update(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
            return false;
        }

        $role = $user->roleIn($tenant);
        if (in_array($role, [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value], true)) {
            return true;
        }

        if ($role === TenantRole::SalesRep->value) {
            return $lead->assigned_user_id === null || (int) $lead->assigned_user_id === (int) $user->id;
        }

        return false;
    }

    public function delete(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }

    public function assign(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }
}
