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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $lead->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'leads.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
            return false;
        }

        if (! $user->hasPermissionIn($tenant, 'leads.update')) {
            return false;
        }

        if ($user->roleIn($tenant) === TenantRole::SalesRep->value) {
            return $lead->assigned_user_id === null || (int) $lead->assigned_user_id === (int) $user->id;
        }

        return true;
    }

    public function delete(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $lead->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'leads.delete');
    }

    public function assign(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $lead->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'leads.assign');
    }

    public function convert(User $user, Lead $lead): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $lead->organization_id !== (int) $tenant->id) {
            return false;
        }

        if (! $user->hasPermissionIn($tenant, 'leads.convert')) {
            return false;
        }

        if ($user->roleIn($tenant) === TenantRole::SalesRep->value) {
            return $lead->assigned_user_id === null || (int) $lead->assigned_user_id === (int) $user->id;
        }

        return true;
    }
}
