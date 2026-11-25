<?php

namespace App\Modules\Communications\WhatsApp\Jobs;

use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Communications\WhatsApp\Services\CoexistenceService;
use App\Modules\Communications\WhatsApp\Services\ConsentService;
use App\Modules\Notifications\Notifications\InboundWhatsAppNotification;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\Touchpoint;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(
        CoexistenceService $coexistenceService,
        ConsentService $consentService,
        TenantContext $tenantContext,
    ): void {
        $previousTenant = $tenantContext->getTenant();

        try {
            $entries = $this->payload['entry'] ?? [];
            foreach ($entries as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $field = (string) ($change['field'] ?? '');
                    $value = $change['value'] ?? [];
                    $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                    if ($phoneNumberId === '') {
                        continue;
                    }

                    $account = WhatsAppAccount::withoutGlobalScope(OrganizationScope::class)
                        ->where('phone_number_id', $phoneNumberId)
                        ->where('is_active', true)
                        ->first();

                    if (! $account) {
                        continue;
                    }

                    if ($field === 'smb_message_echoes' || ! empty($value['message_echoes'])) {
                        foreach ($value['message_echoes'] ?? [] as $echo) {
                            $coexistenceService->handleEchoMessage($account, $echo);
                        }
                        continue;
                    }

                    foreach ($value['statuses'] ?? [] as $statusEntry) {
                        $coexistenceService->reconcileStatusUpdate((int) $account->organization_id, $statusEntry);
                    }

                    $contactsMap = [];
                    foreach ($value['contacts'] ?? [] as $c) {
                        $waId = (string) ($c['wa_id'] ?? '');
                        if ($waId !== '') {
                            $contactsMap[$waId] = (string) ($c['profile']['name'] ?? 'WhatsApp User');
                        }
                    }

                    foreach ($value['messages'] ?? [] as $msg) {
                        $this->processInboundMessage($account, $msg, $contactsMap, $consentService);
                    }
                }
            }
        } finally {
            $tenantContext->setTenant($previousTenant);
        }
    }

    /**
     * @param  array<string, mixed>  $msg
     * @param  array<string, string>  $contactsMap
     */
    private function processInboundMessage(
        WhatsAppAccount $account,
        array $msg,
        array $contactsMap,
        ConsentService $consentService,
    ): void {
        $wamid = (string) ($msg['id'] ?? '');
        if ($wamid !== '' && WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->where('meta_message_id', $wamid)->exists()) {
            return;
        }

        $fromRaw = (string) ($msg['from'] ?? '');
        $phone = str_starts_with($fromRaw, '+') ? $fromRaw : '+'.$fromRaw;
        $digits = ltrim($phone, '+');
        $profileName = $contactsMap[$digits] ?? $contactsMap[$fromRaw] ?? 'WhatsApp User';
        $timestamp = isset($msg['timestamp']) ? Carbon::createFromTimestamp((int) $msg['timestamp']) : now();

        $contact = Contact::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $account->organization_id)
            ->whereIn('phone', [$phone, $digits])
            ->first();

        if (! $contact) {
            $parts = explode(' ', trim($profileName), 2);
            $contact = Contact::withoutGlobalScope(OrganizationScope::class)->create([
                'organization_id' => $account->organization_id,
                'first_name' => $parts[0] ?: 'WhatsApp',
                'last_name' => $parts[1] ?? null,
                'phone' => $phone,
                'preferred_channel' => 'whatsapp',
            ]);
        }

        $conversation = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            [
                'organization_id' => $account->organization_id,
                'whatsapp_account_id' => $account->id,
                'contact_id' => $contact->id,
            ],
            [
                'customer_phone' => $phone,
                'last_inbound_at' => $timestamp,
                'last_message_at' => $timestamp,
            ],
        );

        $conversation->update([
            'last_inbound_at' => $timestamp,
            'last_message_at' => $timestamp,
        ]);

        $type = (string) ($msg['type'] ?? 'text');
        $body = $msg['text']['body'] ?? ($msg['button']['text'] ?? null);

        $waMessage = WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $account->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'meta_message_id' => $wamid !== '' ? $wamid : null,
            'direction' => 'inbound',
            'message_type' => $type,
            'body' => $body,
            'status' => 'delivered',
            'sent_at' => $timestamp,
        ]);

        // Notify assigned deal owner(s) of inbound WhatsApp message
        $ownerIds = Deal::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $account->organization_id)
            ->where('contact_id', $contact->id)
            ->whereNotNull('assigned_user_id')
            ->pluck('assigned_user_id')
            ->unique();

        foreach ($ownerIds as $ownerId) {
            $owner = User::find($ownerId);
            if ($owner) {
                $owner->notify(new InboundWhatsAppNotification($waMessage, $phone));
            }
        }

        $consentService->processInboundKeyword((int) $account->organization_id, $contact, $phone, $body);

        // If an outbound message already existed on this conversation, record conversation_qualified touchpoint if linked to a tracking link
        $hasOutbound = WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->exists();

        if ($hasOutbound) {
            $priorTouchpoint = Touchpoint::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $account->organization_id)
                ->where('contact_id', $contact->id)
                ->whereNotNull('tracking_link_id')
                ->first();

            if ($priorTouchpoint) {
                Touchpoint::withoutGlobalScope(OrganizationScope::class)->firstOrCreate([
                    'organization_id' => $account->organization_id,
                    'tracking_link_id' => $priorTouchpoint->tracking_link_id,
                    'contact_id' => $contact->id,
                    'event_milestone' => 'conversation_qualified',
                ], [
                    'link_click_id' => $priorTouchpoint->link_click_id,
                    'lead_id' => $priorTouchpoint->lead_id,
                    'occurred_at' => $timestamp,
                ]);
            }
        }
    }
}
