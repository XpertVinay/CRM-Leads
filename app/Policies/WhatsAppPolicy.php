<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;

class WhatsAppPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function manageSettings(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null) {
            return false;
        }

        return $user->hasRoleIn($tenant, [TenantRole::OrgAdmin->value]);
    }

    public function viewConversations(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null) {
            return false;
        }

        return in_array($user->roleIn($tenant), TenantRole::values(), true);
    }

    public function sendMessage(User $user, ?WhatsAppConversation $conversation = null): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null) {
            return false;
        }

        if ($conversation !== null && (int) $conversation->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [
            TenantRole::OrgAdmin->value,
            TenantRole::SalesManager->value,
            TenantRole::SalesRep->value,
        ]);
    }

    public function manageConsent(User $user): bool
    {
        return $this->sendMessage($user);
    }

    public function manageAccount(User $user, WhatsAppAccount $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || (int) $account->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRoleIn($tenant, [TenantRole::OrgAdmin->value]);
    }
}
