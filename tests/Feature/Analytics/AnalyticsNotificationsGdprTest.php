<?php

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Livewire\AttributionReportView;
use App\Modules\Analytics\Livewire\FunnelVelocityDashboard;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class AnalyticsNotificationsGdprTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_executive_attribution_productivity_and_whatsapp_health_dashboards(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $openStage = $pipeline->stages()->where('category', 'open')->firstOrFail();
        $wonStage = $pipeline->stages()->where('category', 'won')->firstOrFail();

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Exec',
            'email' => 'exec@example.test',
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'exec-ad',
            'name' => 'Exec Ad',
            'destination_url' => 'https://example.test',
            'channel' => 'ad',
            'ad_spend' => 500,
        ]);

        $deal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $admin->id,
            'title' => 'Won Exec Deal',
            'amount' => 2500,
            'stage_entered_at' => now()->subDays(3),
        ]);

        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link->id,
            'contact_id' => $contact->id,
            'deal_id' => $deal->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now()->subDays(2),
        ]);

        $this->actingAsOrgMember($admin, $org)
            ->patchJson(route('ajax.deals.stage', $deal), ['pipeline_stage_id' => $wonStage->id])
            ->assertOk();

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.analytics.executive'))
            ->assertOk()
            ->assertJsonPath('data.won_count', 1)
            ->assertJsonPath('data.win_rate', 100);

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.analytics.attribution', ['model' => 'last_touch']))
            ->assertOk()
            ->assertJsonPath('data.totals.roas', 5);

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.analytics.productivity'))
            ->assertOk()
            ->assertJsonPath('data.0.won_deals_count', 1);

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.analytics.whatsapp-health'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['delivery_rate', 'read_rate', 'active_windows_count']]);

        $this->actingAsOrgMember($admin, $org);
        Livewire::test(FunnelVelocityDashboard::class)->assertSee('100%');
        Livewire::test(AttributionReportView::class)->assertSee('Exec Ad');
    }

    public function test_notifications_and_gdpr_export_anonymization_and_audit_logs(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $rep = \App\Models\User::factory()->create();
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);

        $this->withTenant($org);

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2B',
            'first_name' => 'Privacy',
            'last_name' => 'Subject',
            'email' => 'privacy@example.test',
            'phone' => '+15557778888',
            'company_name' => 'Privacy Corp',
        ]);

        // Assigning lead triggers LeadAssignedNotification + AuditLog
        $this->actingAsOrgMember($admin, $org)
            ->patchJson(route('ajax.leads.assign', $lead), ['assigned_user_id' => $rep->id])
            ->assertOk();

        $notifResp = $this->actingAsOrgMember($rep, $org)
            ->getJson(route('ajax.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $notifId = $notifResp->json('data.0.id');
        $this->actingAsOrgMember($rep, $org)
            ->patchJson(route('ajax.notifications.read', $notifId))
            ->assertOk();

        $this->actingAsOrgMember($rep, $org)
            ->getJson(route('ajax.notifications.index'))
            ->assertJsonPath('unread_count', 0);

        // GDPR Export & Anonymize preserves deal financial amount
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $wonStage = $pipeline->stages()->where('category', 'won')->firstOrFail();

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Privacy',
            'last_name' => 'Subject',
            'email' => 'privacy@example.test',
            'phone' => '+15557778888',
        ]);

        $deal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $wonStage->id,
            'contact_id' => $contact->id,
            'title' => 'Preserved Deal',
            'amount' => 12000,
            'closed_at' => now(),
        ]);

        $waAccount = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_gdpr',
            'phone_number_id' => 'pn_gdpr',
            'display_phone_number' => '+15550009999',
            'access_token_encrypted' => Crypt::encryptString('tok'),
            'is_active' => true,
        ]);

        $conv = WhatsAppConversation::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contact->id,
            'customer_phone' => '+15557778888',
        ]);

        WhatsAppMessage::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $conv->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Private customer message',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        WhatsAppConsent::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'phone' => '+15557778888',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'gdpr-link',
            'name' => 'GDPR Link',
            'destination_url' => 'https://example.test',
            'channel' => 'email',
        ]);

        $click = LinkClick::create([
            'tracking_link_id' => $link->id,
            'click_token' => 'tok-gdpr-1',
            'ip_hash' => hash('sha256', '1.2.3.4'),
            'user_agent' => 'SecretUA',
            'clicked_at' => now(),
        ]);

        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link->id,
            'link_click_id' => $click->id,
            'contact_id' => $contact->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now(),
        ]);

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.contacts.gdpr-export', $contact))
            ->assertOk()
            ->assertSee('privacy@example.test')
            ->assertSee('Private customer message');

        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.contacts.gdpr-anonymize', $contact))
            ->assertOk();

        $this->assertSame('Anonymized', $contact->fresh()->first_name);
        $this->assertNull($contact->fresh()->phone);
        $this->assertSame('[REDACTED]', WhatsAppMessage::withoutGlobalScopes()->where('whatsapp_conversation_id', $conv->id)->value('body'));
        $this->assertSame(12000.0, (float) $deal->fresh()->amount);
        $this->assertNotNull($deal->fresh()->closed_at);

        $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.audit-logs.index'))
            ->assertOk()
            ->assertSee('gdpr_anonymize_contact');
    }
}
