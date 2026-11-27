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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $contact->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'contacts.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $contact->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $contact->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'contacts.delete');
    }
}
