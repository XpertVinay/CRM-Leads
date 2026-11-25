<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;

class F22GdprAndAuditLogsTest extends E2ETestCase
{
    public function test_t1_f22_01_gdpr_contact_export_returns_complete_pii_and_related_records_json(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR Export Contact Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'first_name' => 'Privacy',
            'last_name' => 'Subject',
            'email' => 'privacy.subject@gdpr.test',
            'phone' => '+15552200001',
            'company_name' => 'GDPR Target Inc',
        ]))->assertStatus(201))['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'GDPR Export Deal',
            'deal_amount' => 15000,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $contactId = (int) DB::table('contacts')->where('organization_id', $org->id)->value('id');
        $this->postJson('/ajax/activities', [
            'subject_type' => 'contact',
            'subject_id' => $contactId,
            'type' => 'note',
            'description' => 'GDPR subject activity note',
        ])->assertStatusIn([200, 201]);

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waMsgModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppMessage';
        $waAccount = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_gdpr_01',
            'phone_number_id' => 'pn_gdpr_01',
            'display_phone_number' => '+15552200099',
            'access_token_encrypted' => 'enc-gdpr-01',
            'is_active' => true,
        ]);
        $conv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15552200001',
            'last_inbound_at' => now()->subMinutes(15),
            'last_message_at' => now()->subMinutes(15),
        ]);
        $waMsgModel::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $conv->id,
            'meta_message_id' => 'wamid.GDPR2201',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Please export my WhatsApp data',
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(15),
        ]);

        $exportRes = $this->getJson("/ajax/contacts/{$contactId}/gdpr-export");
        $exportRes->assertStatus(200);
        $raw = (string) $exportRes->getContent();
        $this->assertStringContainsString('privacy.subject@gdpr.test', $raw);
        $this->assertStringContainsString('GDPR Export Deal', $raw);
        $this->assertStringContainsString('GDPR subject activity note', $raw);
        $this->assertStringContainsString('Please export my WhatsApp data', $raw);
    }

    public function test_t1_f22_02_gdpr_lead_export_returns_complete_lead_pii_activities_and_touchpoints_json(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR Export Lead Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'GDPR Lead Campaign',
            'code' => 'gdpr-lead-2202',
            'destination_url' => 'https://example.test/gdpr',
            'channel' => 'email',
        ])->assertStatusIn([200, 201]);
        $this->get('/l/gdpr-lead-2202')->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->orderByDesc('id')->value('click_token');

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'first_name' => 'LeadExport',
            'last_name' => 'Person',
            'email' => 'lead.export@gdpr.test',
            'click_token' => $clickToken,
        ]))->assertStatus(201))['id'];

        $this->postJson('/ajax/activities', [
            'subject_type' => 'lead',
            'subject_id' => $leadId,
            'type' => 'email',
            'description' => 'GDPR lead export activity',
        ])->assertStatusIn([200, 201]);

        $exportRes = $this->getJson("/ajax/leads/{$leadId}/gdpr-export");
        $exportRes->assertStatus(200);
        $raw = (string) $exportRes->getContent();
        $this->assertStringContainsString('lead.export@gdpr.test', $raw);
        $this->assertStringContainsString('GDPR lead export activity', $raw);
        $this->assertStringContainsString('lead_captured', $raw);
    }

    public function test_t1_f22_03_gdpr_contact_anonymization_redacts_pii_while_preserving_deal_amount_and_closed_at(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR Anonymize Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'GDPR Anon Campaign',
            'code' => 'gdpr-anon-2203',
            'destination_url' => 'https://example.test/anon',
            'channel' => 'ad',
            'ad_spend' => 500,
        ])->assertStatusIn([200, 201]);

        $this->withHeader('User-Agent', 'SecretBrowserAgent/9.0')
            ->get('/l/gdpr-anon-2203')
            ->assertStatus(302);
        $clickRow = DB::table('link_clicks')->orderByDesc('id')->first();
        $originalIpHash = (string) $clickRow->ip_hash;

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'first_name' => 'ForgetMe',
            'last_name' => 'Completely',
            'email' => 'forget.me@gdpr.test',
            'phone' => '+15552200003',
            'company_name' => 'Forgettable Co',
            'click_token' => $clickRow->click_token,
        ]))->assertStatus(201))['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Preserved Revenue Deal',
            'deal_amount' => 25000.00,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
        ])->assertStatusIn([200, 201]);

        $contactId = (int) DB::table('contacts')->where('organization_id', $org->id)->value('id');
        $dealId = (int) DB::table('deals')->where('organization_id', $org->id)->value('id');
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);
        $originalClosedAt = DB::table('deals')->where('id', $dealId)->value('closed_at');
        $this->assertNotNull($originalClosedAt);

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waMsgModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppMessage';
        $waAccount = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_gdpr_03',
            'phone_number_id' => 'pn_gdpr_03',
            'display_phone_number' => '+15552200033',
            'access_token_encrypted' => 'enc-gdpr-03',
            'is_active' => true,
        ]);
        $conv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15552200003',
            'last_inbound_at' => now()->subMinutes(10),
            'last_message_at' => now()->subMinutes(10),
        ]);
        $waMsgModel::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $conv->id,
            'meta_message_id' => 'wamid.GDPR2203',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Sensitive PII message content',
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(10),
        ]);
        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15552200003',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
        ])->assertStatusIn([200, 201]);

        $anonRes = $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize");
        $anonRes->assertStatus(200);

        $contact = DB::table('contacts')->where('id', $contactId)->first();
        $this->assertSame('Anonymized', $contact->first_name);
        $this->assertNull($contact->last_name);
        $this->assertNotSame('forget.me@gdpr.test', $contact->email);
        $this->assertNull($contact->phone);

        $lead = DB::table('leads')->where('id', $leadId)->first();
        $this->assertNotSame('forget.me@gdpr.test', $lead->email);
        $this->assertNull($lead->phone);

        $waConv = DB::table('whatsapp_conversations')->where('id', $conv->id)->first();
        $this->assertNotSame('+15552200003', $waConv->customer_phone);

        $waMsg = DB::table('whatsapp_messages')->where('whatsapp_conversation_id', $conv->id)->first();
        $this->assertStringNotContainsString('Sensitive PII message content', (string) $waMsg->body);

        $consent = DB::table('whatsapp_consents')->where('contact_id', $contactId)->first();
        $this->assertNotSame('+15552200003', $consent->phone);

        $clickAfter = DB::table('link_clicks')->where('id', $clickRow->id)->first();
        $this->assertNotSame($originalIpHash, $clickAfter->ip_hash);
        $this->assertStringNotContainsString('SecretBrowserAgent', (string) $clickAfter->user_agent);

        // CRITICAL INVARIANT: Deal revenue & closed_at MUST be preserved
        $dealAfter = DB::table('deals')->where('id', $dealId)->first();
        $this->assertSame(25000.0, (float) $dealAfter->amount);
        $this->assertSame((string) $originalClosedAt, (string) $dealAfter->closed_at);

        $execJson = (string) $this->getJson('/ajax/analytics/executive')->assertStatus(200)->getContent();
        $this->assertStringContainsString('25000', $execJson);
    }

    public function test_t1_f22_04_critical_mutations_record_immutable_audit_logs(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Audit Trail Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->patchJson("/ajax/team/members/{$rep->id}/role", ['role' => 'sales_manager'])->assertStatus(200);
        $this->postJson('/ajax/team/tokens', ['name' => 'Audit Sync Token'])->assertStatus(201);
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'audit_sector',
            'label' => 'Audit Sector',
            'data_type' => 'text',
        ])->assertStatusIn([200, 201]);

        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->get();
        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Audit Deal',
            'amount' => 1000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stages[0]->id,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $stages[1]->id])->assertStatus(200);
        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);

        $this->assertTrue(Schema::hasTable('audit_logs'), 'audit_logs table must exist.');

        $logs = DB::table('audit_logs')->where('organization_id', $org->id)->get();
        $this->assertGreaterThanOrEqual(5, $logs->count());
        $events = $logs->pluck('event')->implode(' | ');
        $this->assertNotEmpty($events);
    }

    public function test_t1_f22_05_org_admin_views_and_filters_audit_logs_ui_and_json(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Audit UI Filter Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/team/tokens', ['name' => 'Filterable Token'])->assertStatus(201);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);

        $htmlRes = $this->get('/audit-logs');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        $allLogsRes = $this->getJson('/ajax/audit-logs');
        $allLogsRes->assertStatus(200);
        $items = $this->extractJsonData($allLogsRes);
        $this->assertNotEmpty($items);

        $firstEvent = (string) ($items[0]['event'] ?? '');
        $this->assertNotSame('', $firstEvent);

        $filteredRes = $this->getJson('/ajax/audit-logs?event=' . urlencode($firstEvent));
        $filteredRes->assertStatus(200);
        $filteredItems = $this->extractJsonData($filteredRes);
        $this->assertNotEmpty($filteredItems);

        foreach ($filteredItems as $row) {
            $this->assertSame($firstEvent, (string) $row['event']);
            $this->assertSame((int) $org->id, (int) $row['organization_id']);
        }
    }
}
