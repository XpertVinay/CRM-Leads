<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tracking\Models\TrackingLink;

class TrackingLinkPolicy
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

        return $tenant !== null && $user->hasPermissionIn($tenant, 'tracking_links.view');
    }

    public function view(User $user, TrackingLink $trackingLink): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $trackingLink->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'tracking_links.view');
    }

    public function create(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'tracking_links.create');
    }

    public function update(User $user, TrackingLink $trackingLink): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $trackingLink->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'tracking_links.update');
    }

    public function delete(User $user, TrackingLink $trackingLink): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $trackingLink->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'tracking_links.delete');
    }
}
