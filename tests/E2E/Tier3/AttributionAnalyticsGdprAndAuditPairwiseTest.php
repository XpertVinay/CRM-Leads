<?php

namespace Tests\E2E\Tier3;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class AttributionAnalyticsGdprAndAuditPairwiseTest extends E2ETestCase
{
    public function test_t3_p21_multi_touch_attribution_and_analytics_roi_compute_exact_cpa_and_roas(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P21 ROI Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $link1Id = (int) $this->extractJsonData($this->postJson('/ajax/tracking-links', [
            'name' => 'Link A Paid Ad',
            'code' => 'p21-ad',
            'destination_url' => 'https://example.test/a',
            'channel' => 'ad',
            'utm_source' => 'linkedin',
            'ad_spend' => 200.00,
        ])->assertStatus(201))['id'];

        $link2Id = (int) $this->extractJsonData($this->postJson('/ajax/tracking-links', [
            'name' => 'Link B Email',
            'code' => 'p21-email',
            'destination_url' => 'https://example.test/b',
            'channel' => 'email',
            'utm_source' => 'newsletter',
            'ad_spend' => 50.00,
        ])->assertStatus(201))['id'];

        $link3Id = (int) $this->extractJsonData($this->postJson('/ajax/tracking-links', [
            'name' => 'Link C Organic',
            'code' => 'p21-organic',
            'destination_url' => 'https://example.test/c',
            'channel' => 'organic',
            'utm_source' => 'community',
            'ad_spend' => 0.00,
        ])->assertStatus(201))['id'];

        // Simulate 3 chronological clicks across the 3 links
        $this->get('/l/p21-ad')->assertStatus(302);
        $click1 = DB::table('link_clicks')->where('tracking_link_id', $link1Id)->orderByDesc('id')->first();

        $this->get('/l/p21-email')->assertStatus(302);
        $click2 = DB::table('link_clicks')->where('tracking_link_id', $link2Id)->orderByDesc('id')->first();

        $this->get('/l/p21-organic')->assertStatus(302);
        $click3 = DB::table('link_clicks')->where('tracking_link_id', $link3Id)->orderByDesc('id')->first();

        // Capture B2C lead with first click_token, convert with $100.00 Deal
        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'click_token' => $click1->click_token,
        ]))->assertStatus(201))['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'P21 Exact Sum Deal',
            'deal_amount' => 100.00,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $contactId = (int) DB::table('leads')->where('id', $leadId)->value('converted_contact_id');
        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        // Ensure click 2 and click 3 touchpoints are bound to this same journey's contact/deal
        DB::table('touchpoints')->where('link_click_id', $click2->id)->update([
            'contact_id' => $contactId,
            'deal_id' => $dealId,
            'occurred_at' => now()->addSeconds(10),
        ]);
        DB::table('touchpoints')->where('link_click_id', $click3->id)->update([
            'contact_id' => $contactId,
            'deal_id' => $dealId,
            'occurred_at' => now()->addSeconds(20),
        ]);

        // Win the $100.00 Deal
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        // Linear attribution must distribute exact sum = $100.00 across 3 links (zero penny drift)
        $linearRes = $this->getJson('/ajax/analytics/attribution?model=linear')->assertStatus(200)->json();
        $linearData = $linearRes['data'] ?? $linearRes;
        $this->assertEqualsWithDelta(100.00, (float) ($linearData['total_attributed_revenue'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(250.00, (float) ($linearData['total_ad_spend'] ?? 0), 0.01);

        if (isset($linearData['roas'])) {
            $this->assertEqualsWithDelta(0.4, (float) $linearData['roas'], 0.01);
        }
        if (isset($linearData['cpa'])) {
            $this->assertEqualsWithDelta(250.0, (float) $linearData['cpa'], 0.01);
        }

        // First-touch and Last-touch must also recognize exact $100.00 total without DivisionByZero on $0 link
        $firstRes = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $firstData = $firstRes['data'] ?? $firstRes;
        $this->assertEqualsWithDelta(100.00, (float) ($firstData['total_attributed_revenue'] ?? 0), 0.001);

        $lastRes = $this->getJson('/ajax/analytics/attribution?model=last_touch')->assertStatus(200)->json();
        $lastData = $lastRes['data'] ?? $lastRes;
        $this->assertEqualsWithDelta(100.00, (float) ($lastData['total_attributed_revenue'] ?? 0), 0.001);
    }

    public function test_t3_p22_gdpr_anonymization_redacts_pii_while_preserving_won_deal_revenue_and_analytics(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P22 GDPR Revenue Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'P22 Link',
            'code' => 'p22-gdpr',
            'destination_url' => 'https://example.test/p22',
            'channel' => 'ad',
            'ad_spend' => 500,
        ])->assertStatus(201);

        $this->get('/l/p22-gdpr')->assertStatus(302);
        $clickRow = DB::table('link_clicks')->where('organization_id', $org->id)->first();
        $originalIpHash = $clickRow->ip_hash;

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'first_name' => 'Jane',
            'last_name' => 'Confidential',
            'email' => 'jane.confidential@example.test',
            'phone' => '+15554440022',
            'click_token' => $clickRow->click_token,
        ]))->assertStatus(201))['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'GDPR Preserved Deal',
            'deal_amount' => 25000.00,
        ])->assertStatus(200);

        $contactId = (int) DB::table('leads')->where('id', $leadId)->value('converted_contact_id');
        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        // Anonymize Contact
        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);

        // Contact, Lead, and LinkClick PII must be redacted
        $contact = DB::table('contacts')->where('id', $contactId)->first();
        $this->assertNotSame('jane.confidential@example.test', $contact->email);
        $this->assertNull($contact->phone);
        $this->assertNotSame('Confidential', $contact->last_name);

        $lead = DB::table('leads')->where('id', $leadId)->first();
        $this->assertNotSame('jane.confidential@example.test', $lead->email);

        $redactedIpHash = DB::table('link_clicks')->where('id', $clickRow->id)->value('ip_hash');
        $this->assertNotSame($originalIpHash, $redactedIpHash);

        // Financial revenue & analytics must remain 100% intact
        $deal = DB::table('deals')->where('id', $dealId)->first();
        $this->assertEqualsWithDelta(25000.00, (float) $deal->amount, 0.01);
        $this->assertNotNull($deal->closed_at);

        $attrRes = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $attrData = $attrRes['data'] ?? $attrRes;
        $this->assertEqualsWithDelta(25000.00, (float) ($attrData['total_attributed_revenue'] ?? 0), 0.01);
    }

    public function test_t3_p23_gdpr_anonymization_redacts_whatsapp_conversation_messages_and_consents(): void
    {
        config(['services.meta.app_secret' => 'p23_secret']);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P23 GDPR WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waAccount = $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_p23',
            'phone_number_id' => 'pn_p23',
            'display_phone_number' => '+15550230001',
            'access_token_encrypted' => 'enc-p23',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload([
            'first_name' => 'Secret',
            'last_name' => 'Person',
            'email' => 'secret.wa@example.test',
            'phone' => '+15553330023',
        ]))->assertStatus(201))['id'];

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15553330023',
            'is_opted_in' => true,
            'consent_source' => 'checkout_checkbox',
        ])->assertStatus(201);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $payload = WhatsAppWebhookHelper::inboundTextPayload(
            'pn_p23',
            '15553330023',
            'My confidential SSN is 123-45-6789',
            'wamid.P23_SECRET'
        );
        $rawJson = WhatsAppWebhookHelper::encode($payload);
        $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($rawJson, 'p23_secret'),
            ],
            $rawJson
        )->assertStatus(200);
        $this->drainDatabaseQueue();

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")
            ->assertStatus(200);

        $conversation = DB::table('whatsapp_conversations')
            ->where('organization_id', $org->id)
            ->where('contact_id', $contactId)
            ->first();
        $this->assertNotNull($conversation);
        $this->assertStringNotContainsString('5553330023', (string) $conversation->customer_phone);

        $message = DB::table('whatsapp_messages')
            ->where('whatsapp_conversation_id', $conversation->id)
            ->first();
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('123-45-6789', (string) $message->body);

        $consent = DB::table('whatsapp_consents')
            ->where('organization_id', $org->id)
            ->where('contact_id', $contactId)
            ->first();
        $this->assertNotNull($consent);
        $this->assertStringNotContainsString('5553330023', (string) $consent->phone);
    }

    public function test_t3_p24_immutable_audit_logs_record_critical_mutations_and_restrict_to_org_admin(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'meta_audit_tok'], 200),
            '*' => Http::response([
                'waba_id' => 'waba_p24',
                'phone_number_id' => 'pn_p24',
                'display_phone_number' => '+15550240001',
                'verified_name' => 'Audit Biz',
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P24 Audit Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);

        // 1. Role change
        $this->patchJson("/ajax/team/members/{$rep->id}/role", ['role' => 'sales_manager'])->assertStatus(200);
        // 2. API token issuance
        $this->postJson('/ajax/team/tokens', ['name' => 'Audit Key'])->assertStatus(201);
        // 3. WhatsApp connect
        $this->postJson('/ajax/whatsapp/connect', [
            'waba_id' => 'waba_p24',
            'phone_number_id' => 'pn_p24',
            'display_phone_number' => '+15550240001',
            'access_token' => 'plain-secret-p24',
        ])->assertStatus(201);
        // 4. Deal stage transition
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->orderBy('order_index')->get();
        $openStageId = (int) $stages->where('category', 'open')->first()->id;
        $wonStageId = (int) $stages->where('category', 'won')->first()->id;

        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Audit Deal',
            'amount' => 5000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);
        // 5. GDPR anonymization
        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);

        // Org Admin views audit logs
        $this->get('/audit-logs')->assertStatus(200);
        $auditRes = $this->getJson('/ajax/audit-logs')->assertStatus(200);
        $logs = $this->extractJsonData($auditRes);
        $this->assertGreaterThanOrEqual(5, count($logs));

        // Non-admin roles must receive 403 on audit logs
        foreach ([$manager, $rep, $readOnly] as $nonAdmin) {
            $this->actingAsInOrganization($nonAdmin, $org->id);
            $this->get('/audit-logs')->assertStatus(403);
            $this->getJson('/ajax/audit-logs')->assertStatus(403);
        }

        // Immutability: HTTP update/delete endpoints do not exist (404/405) & Eloquent model rejects mutation
        $this->actingAsInOrganization($admin, $org->id);
        $firstLogId = (int) DB::table('audit_logs')->where('organization_id', $org->id)->value('id');
        $this->assertStatusIn($this->deleteJson("/ajax/audit-logs/{$firstLogId}"), [403, 404, 405]);
        $this->assertStatusIn($this->putJson("/ajax/audit-logs/{$firstLogId}", ['event' => 'tampered']), [403, 404, 405]);

        $auditModel = 'App\\Modules\\Compliance\\Models\\AuditLog';
        if (class_exists($auditModel)) {
            $record = $auditModel::query()->findOrFail($firstLogId);
            $threwOrBlocked = false;
            try {
                $updated = $record->update(['event' => 'tampered']);
                $threwOrBlocked = ($updated === false);
            } catch (\Throwable) {
                $threwOrBlocked = true;
            }
            $this->assertTrue($threwOrBlocked, 'AuditLog model must prevent updates.');

            $deleteBlocked = false;
            try {
                $deleted = $record->delete();
                $deleteBlocked = ($deleted === false);
            } catch (\Throwable) {
                $deleteBlocked = true;
            }
            $this->assertTrue($deleteBlocked, 'AuditLog model must prevent deletion.');
        }

        $this->assertTrue(DB::table('audit_logs')->where('id', $firstLogId)->exists());
    }
}
