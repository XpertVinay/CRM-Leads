<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class CampaignPolicy
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

    public function view(User $user, Campaign $campaign): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $campaign->organization_id === (int) $tenant->id
            && in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
        ]);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $campaign->organization_id === (int) $tenant->id
            && $user->hasRoleIn($tenant, [
                TenantRole::OrgAdmin->value,
                TenantRole::SalesManager->value,
            ]);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
