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
            if (in_array($ability, ['viewConversations', 'sendMessage', 'manageConsent'], true)) {
                $tenant = app(TenantContext::class)->getTenant();

                return $tenant !== null && WhatsAppAccount::isConfiguredForOrganization((int) $tenant->id);
            }

            return true;
        }

        return null;
    }

    public function manageSettings(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null && $user->hasPermissionIn($tenant, 'whatsapp.settings');
    }

    public function viewConversations(User $user): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && WhatsAppAccount::isConfiguredForOrganization((int) $tenant->id)
            && $user->hasPermissionIn($tenant, 'whatsapp.view');
    }

    public function sendMessage(User $user, ?WhatsAppConversation $conversation = null): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        if ($tenant === null || ! WhatsAppAccount::isConfiguredForOrganization((int) $tenant->id)) {
            return false;
        }

        if ($conversation !== null && (int) $conversation->organization_id !== (int) $tenant->id) {
            return false;
        }

        return $user->hasPermissionIn($tenant, 'whatsapp.send');
    }

    public function manageConsent(User $user): bool
    {
        return $this->sendMessage($user);
    }

    public function manageAccount(User $user, WhatsAppAccount $account): bool
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant !== null
            && (int) $account->organization_id === (int) $tenant->id
            && $user->hasPermissionIn($tenant, 'whatsapp.settings');
    }
}
