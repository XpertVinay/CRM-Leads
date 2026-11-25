<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class ContactPolicy
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

    public function view(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $contact->organization_id !== (int) $tenant->id) {
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

    public function update(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $contact->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
            TenantRole::SalesRep->value,
        ]);
    }

    public function delete(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $contact->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }
}
