<?php

namespace App\Modules\Communications\WhatsApp\Services;

use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Support\Security\DataEncrypter;
use Carbon\Carbon;

class CoexistenceService
{
    /**
     * @param  array<string, mixed>  $echo
     */
    public function handleEchoMessage(WhatsAppAccount $account, array $echo): ?WhatsAppMessage
    {
        if (! $account->is_coexistence_enabled) {
            return null;
        }

        $wamid = (string) ($echo['id'] ?? '');
        if ($wamid !== '' && WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->where('meta_message_id', $wamid)->exists()) {
            return null;
        }

        $toRaw = (string) ($echo['to'] ?? '');
        $phone = str_starts_with($toRaw, '+') ? $toRaw : '+'.$toRaw;
        $timestamp = isset($echo['timestamp']) ? Carbon::createFromTimestamp((int) $echo['timestamp']) : now();

        $contact = $this->findOrCreateContact((int) $account->organization_id, $phone);
        $conversation = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            [
                'organization_id' => $account->organization_id,
                'whatsapp_account_id' => $account->id,
                'contact_id' => $contact->id,
            ],
            [
                'customer_phone' => $phone,
                'last_message_at' => $timestamp,
            ],
        );

        $conversation->update(['last_message_at' => $timestamp]);

        $type = (string) ($echo['type'] ?? 'text');
        $body = $echo['text']['body'] ?? null;

        return WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $account->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'meta_message_id' => $wamid !== '' ? $wamid : null,
            'direction' => 'outbound',
            'message_type' => $type,
            'body' => $body,
            'status' => 'sent',
            'is_coexistence_echo' => true,
            'sent_at' => $timestamp,
        ]);
    }

    /**
     * @param  array<string, mixed>  $statusEntry
     */
    public function reconcileStatusUpdate(int $organizationId, array $statusEntry): void
    {
        $wamid = (string) ($statusEntry['id'] ?? '');
        $status = (string) ($statusEntry['status'] ?? '');

        if ($wamid === '' || $status === '') {
            return;
        }

        $message = WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('meta_message_id', $wamid)
            ->first();

        if (! $message) {
            return;
        }

        $updates = ['status' => $status];
        if (! empty($statusEntry['errors'])) {
            $updates['error_payload'] = $statusEntry['errors'];
        }

        $message->update($updates);
    }

    private function findOrCreateContact(int $organizationId, string $phone): Contact
    {
        $digits = ltrim($phone, '+');
        $phoneHashes = array_values(array_unique(array_filter([
            DataEncrypter::instance()->blindIndex($phone, 'phone'),
            DataEncrypter::instance()->blindIndex($digits, 'phone'),
        ])));
        $contact = Contact::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereIn('phone_hash', $phoneHashes)
            ->first();

        if ($contact) {
            return $contact;
        }

        return Contact::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $organizationId,
            'first_name' => 'WhatsApp Contact',
            'phone' => $phone,
            'preferred_channel' => 'whatsapp',
        ]);
    }
}
