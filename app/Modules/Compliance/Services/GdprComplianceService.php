<?php

namespace App\Modules\Compliance\Services;

use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Support\Facades\DB;

class GdprComplianceService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportContact(Contact $contact): array
    {
        $contact->load(['account', 'deals.pipelineStage', 'touchpoints']);

        $activities = Activity::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $contact->organization_id)
            ->where('activitable_type', $contact->getMorphClass())
            ->where('activitable_id', $contact->id)
            ->get();

        $conversations = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->with('messages')
            ->get();

        $consents = WhatsAppConsent::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->get();

        $this->auditService->record('gdpr_export_contact', $contact);

        return [
            'exported_at' => now()->toIso8601String(),
            'contact' => $contact->toArray(),
            'deals' => $contact->deals->toArray(),
            'activities' => $activities->toArray(),
            'touchpoints' => $contact->touchpoints->toArray(),
            'whatsapp_conversations' => $conversations->toArray(),
            'whatsapp_consents' => $consents->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportLead(Lead $lead): array
    {
        $activities = Activity::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $lead->organization_id)
            ->where('activitable_type', $lead->getMorphClass())
            ->where('activitable_id', $lead->id)
            ->get();

        $touchpoints = Touchpoint::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $lead->organization_id)
            ->where('lead_id', $lead->id)
            ->get();

        $this->auditService->record('gdpr_export_lead', $lead);

        return [
            'exported_at' => now()->toIso8601String(),
            'lead' => $lead->toArray(),
            'activities' => $activities->toArray(),
            'touchpoints' => $touchpoints->toArray(),
        ];
    }

    public function anonymizeContact(Contact $contact): Contact
    {
        return DB::transaction(function () use ($contact): Contact {
            $oldEmail = $contact->email;
            $oldPhone = $contact->phone;
            $redactedEmail = 'anonymized-' . $contact->id . '@redacted.invalid';

            $contact->update([
                'first_name' => 'Anonymized',
                'last_name' => null,
                'email' => $redactedEmail,
                'phone' => null,
                'job_title' => null,
                'custom_fields' => null,
            ]);

            // Anonymize originating leads linked to this contact
            $leads = Lead::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contact->organization_id)
                ->where(function ($q) use ($contact, $oldEmail, $oldPhone): void {
                    $q->where('converted_contact_id', $contact->id);
                    if ($oldEmail) {
                        $q->orWhere('email', $oldEmail);
                    }
                    if ($oldPhone) {
                        $q->orWhere('phone', $oldPhone);
                    }
                })
                ->get();

            $leadIds = $leads->pluck('id')->all();
            foreach ($leads as $lead) {
                $lead->update([
                    'first_name' => 'Anonymized',
                    'last_name' => null,
                    'email' => 'anonymized-lead-' . $lead->id . '@redacted.invalid',
                    'phone' => null,
                    'custom_fields' => null,
                ]);
            }

            // Anonymize WhatsApp conversations, messages, and consents
            $conversations = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contact->organization_id)
                ->where('contact_id', $contact->id)
                ->get();

            $convIds = $conversations->pluck('id')->all();
            if ($convIds !== []) {
                foreach ($conversations as $conv) {
                    $conv->update(['customer_phone' => 'redacted-' . $conv->id]);
                }
                WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)
                    ->whereIn('whatsapp_conversation_id', $convIds)
                    ->update(['body' => '[REDACTED]']);
            }

            WhatsAppConsent::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contact->organization_id)
                ->where('contact_id', $contact->id)
                ->update([
                    'phone' => 'redacted-' . $contact->id,
                    'ip_address' => null,
                ]);

            // Anonymize link clicks tied via touchpoints
            $clickIds = Touchpoint::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contact->organization_id)
                ->where(function ($q) use ($contact, $leadIds): void {
                    $q->where('contact_id', $contact->id);
                    if ($leadIds !== []) {
                        $q->orWhereIn('lead_id', $leadIds);
                    }
                })
                ->whereNotNull('link_click_id')
                ->pluck('link_click_id')
                ->all();

            if ($clickIds !== []) {
                LinkClick::whereIn('id', $clickIds)->update([
                    'ip_hash' => str_repeat('0', 64),
                    'user_agent' => '[REDACTED]',
                    'referer' => null,
                ]);
            }

            // Deals (amount, closed_at) are intentionally preserved for financial/accounting integrity
            $this->auditService->record('gdpr_anonymize_contact', $contact, [
                'email_redacted' => $oldEmail !== null,
                'phone_redacted' => $oldPhone !== null,
            ], [
                'first_name' => 'Anonymized',
                'email' => $redactedEmail,
            ]);

            return $contact->fresh();
        });
    }
}
