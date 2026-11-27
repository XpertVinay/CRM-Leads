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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $campaign->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'campaigns.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $campaign->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'campaigns.update');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $campaign->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'campaigns.delete');
    }
}
