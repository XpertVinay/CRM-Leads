<?php

namespace App\Modules\Communications\WhatsApp\Services;

use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Validation\ValidationException;

class ConsentService
{
    public const OPT_OUT_KEYWORDS = ['STOP', 'UNSUBSCRIBE', 'CANCEL'];

    public function recordConsent(
        int $organizationId,
        int $contactId,
        string $phone,
        bool $isOptedIn,
        string $source,
        ?string $ipAddress = null,
    ): WhatsAppConsent {
        return WhatsAppConsent::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'phone' => $phone,
            'is_opted_in' => $isOptedIn,
            'consent_source' => $source,
            'consent_timestamp' => now(),
            'ip_address' => $ipAddress,
        ]);
    }

    public function isOptedIn(int $organizationId, int $contactId): bool
    {
        $latest = WhatsAppConsent::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->orderByDesc('consent_timestamp')
            ->orderByDesc('id')
            ->first();

        return $latest ? (bool) $latest->is_opted_in : false;
    }

    public function assertOptedIn(int $organizationId, int $contactId): void
    {
        if (! $this->isOptedIn($organizationId, $contactId)) {
            throw ValidationException::withMessages([
                'consent' => ['Contact has not opted in or has opted out of WhatsApp communications.'],
            ]);
        }
    }

    public function processInboundKeyword(int $organizationId, Contact $contact, string $phone, ?string $body): bool
    {
        if (! is_string($body)) {
            return false;
        }

        $normalized = strtoupper(trim($body));
        if (in_array($normalized, self::OPT_OUT_KEYWORDS, true)) {
            $this->recordConsent(
                $organizationId,
                $contact->id,
                $phone,
                false,
                'inbound_opt_out_keyword',
            );

            return true;
        }

        return false;
    }
}
