<?php

namespace Tests\E2E\Tier3;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class WhatsAppCoexistenceAndWindowPairwiseTest extends E2ETestCase
{
    public function test_t3_p16_organization_without_whatsapp_runs_full_crm_and_analytics_cleanly(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P16 Zero WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        // WhatsApp pages render gracefully even with 0 WhatsApp accounts
        $this->get('/settings/whatsapp')->assertStatus(200);
        $this->get('/whatsapp')->assertStatus(200);

        // Full Core CRM lifecycle without WhatsApp
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Organic Search',
            'code' => 'p16-organic',
            'destination_url' => 'https://example.test/p16',
            'channel' => 'organic',
            'ad_spend' => 0,
        ])->assertStatus(201);

        $this->get('/l/p16-organic')->assertStatus(302);
        $clickToken = DB::table('link_clicks')->where('organization_id', $org->id)->value('click_token');

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'click_token' => $clickToken,
        ]))->assertStatus(201))['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Zero WA Enterprise Deal',
            'deal_amount' => 12000,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);
        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->postJson('/ajax/activities', [
            'subject_type' => 'deal',
            'subject_id' => $dealId,
            'type' => 'call',
            'description' => 'Closed deal via phone & email only.',
        ])->assertStatus(201);

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        // All 4 analytics endpoints succeed cleanly with zero WhatsApp records
        $this->getJson('/ajax/analytics/executive')->assertStatus(200);
        $this->getJson('/ajax/analytics/attribution')->assertStatus(200);
        $this->getJson('/ajax/analytics/productivity')->assertStatus(200);
        $this->getJson('/ajax/analytics/whatsapp')->assertStatus(200);

        if (Schema::hasTable('whatsapp_accounts')) {
            $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $org->id)->count());
        }
        if (Schema::hasTable('whatsapp_conversations')) {
            $this->assertSame(0, DB::table('whatsapp_conversations')->where('organization_id', $org->id)->count());
        }
        if (Schema::hasTable('whatsapp_messages')) {
            $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $org->id)->count());
        }
    }

    public function test_t3_p17_signed_webhook_uses_database_queue_routes_by_phone_number_id_and_clears_tenant_context(): void
    {
        config(['services.meta.app_secret' => 'p17_secret']);
        $this->enableDatabaseDrivers();

        $orgA = $this->registerOrganizationAndAdmin(['organization_name' => 'P17 WA Org A'])['organization'];
        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'P17 WA Org B'])['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $orgA->id,
            'waba_id' => 'waba_p17_a',
            'phone_number_id' => 'pn_p17_a',
            'display_phone_number' => '+15550170001',
            'access_token_encrypted' => 'enc-a',
            'is_active' => true,
        ]);

        $waModel::create([
            'organization_id' => $orgB->id,
            'waba_id' => 'waba_p17_b',
            'phone_number_id' => 'pn_p17_b',
            'display_phone_number' => '+15550170002',
            'access_token_encrypted' => 'enc-b',
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $payload = WhatsAppWebhookHelper::inboundTextPayload('pn_p17_a', '15559991717', 'Queued for A', 'wamid.P17_1');

        // Invalid signature returns 403 and queues zero jobs
        $this->postSignedWebhook($payload, 'p17_secret', 'sha256=tampered_signature')
            ->assertStatus(403);
        $this->assertSame(0, DB::table('jobs')->count());

        // Valid signature returns 200 and pushes 1 job onto database queue
        $this->postSignedWebhook($payload, 'p17_secret')->assertStatus(200);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->drainDatabaseQueue();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('whatsapp_conversations')->where('organization_id', $orgA->id)->count());
        $this->assertSame(1, DB::table('whatsapp_messages')->where('organization_id', $orgA->id)->count());
        $this->assertSame(0, DB::table('whatsapp_conversations')->where('organization_id', $orgB->id)->count());
        $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $orgB->id)->count());

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->assertFalse($this->app->make($tenantContextClass)->hasTenant());
        }
    }

    public function test_t3_p18_coexistence_echo_deduplicates_wamid_and_does_not_open_expired_24h_window(): void
    {
        config(['services.meta.app_secret' => 'p18_secret']);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P18 Coexistence Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waAccount = $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_p18',
            'phone_number_id' => 'pn_p18',
            'display_phone_number' => '+15550180001',
            'access_token_encrypted' => 'enc-p18',
            'is_coexistence_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload([
            'phone' => '+15550189999',
        ]))->assertStatus(201))['id'];

        $expiredAt = now()->subHours(30)->toDateTimeString();
        $conversationId = DB::table('whatsapp_conversations')->insertGetId([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15550189999',
            'last_inbound_at' => $expiredAt,
            'last_message_at' => $expiredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deliver coexistence mobile echo + replay with same WAMID
        $echoPayload = WhatsAppWebhookHelper::coexistenceEchoPayload(
            'pn_p18',
            '15550189999',
            'Sent from WhatsApp Business mobile app',
            'wamid.ECHO_P18'
        );

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->postSignedWebhook($echoPayload, 'p18_secret')->assertStatus(200);
        $this->drainDatabaseQueue();

        // Exact replay of same WAMID
        $this->postSignedWebhook($echoPayload, 'p18_secret')->assertStatus(200);
        $this->drainDatabaseQueue();

        $echoMessages = DB::table('whatsapp_messages')
            ->where('organization_id', $org->id)
            ->where('meta_message_id', 'wamid.ECHO_P18')
            ->get();

        $this->assertCount(1, $echoMessages, 'Duplicate WAMID coexistence echo must be deduplicated.');
        $this->assertSame('outbound', $echoMessages->first()->direction);
        $this->assertTrue((bool) $echoMessages->first()->is_coexistence_echo);

        // Outbound echo must NOT reset last_inbound_at; free-form text remains blocked outside 24h window
        $this->actingAsInOrganization($admin, $org->id)
            ->postJson("/ajax/whatsapp/conversations/{$conversationId}/send-text", [
                'body' => 'Should be blocked because customer last replied 30h ago',
            ])
            ->assertStatus(422);
    }

    public function test_t3_p19_inbound_stop_opt_out_blocks_even_approved_templates(): void
    {
        config(['services.meta.app_secret' => 'p19_secret']);

        Http::fake([
            '*/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '15550199999', 'wa_id' => '15550199999']],
                'messages' => [['id' => 'wamid.TPL_BEFORE_STOP']],
            ], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P19 OptOut Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waAccount = $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_p19',
            'phone_number_id' => 'pn_p19',
            'display_phone_number' => '+15550190001',
            'access_token_encrypted' => 'enc-p19',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload([
            'phone' => '+15550199999',
        ]))->assertStatus(201))['id'];

        $conversationId = DB::table('whatsapp_conversations')->insertGetId([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15550199999',
            'last_inbound_at' => now()->subHour(),
            'last_message_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::table('whatsapp_templates')->insertGetId([
            'whatsapp_account_id' => $waAccount->id,
            'name' => 'promo_offer',
            'language' => 'en_US',
            'category' => 'marketing',
            'header_type' => 'none',
            'body_text' => 'Hi {{1}}, special offer!',
            'parameter_placeholders' => json_encode(['first_name']),
            'meta_status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15550199999',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
        ])->assertStatus(201);

        // Before STOP: template send succeeds
        $preStopRes = $this->postJson("/ajax/whatsapp/conversations/{$conversationId}/send-template", [
            'template_id' => $templateId,
            'parameters' => ['first_name' => 'Alice'],
        ]);
        $this->assertStatusIn($preStopRes, [200, 201]);

        $sentCountBeforeStop = count(Http::recorded());

        // Customer replies STOP via signed webhook
        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $stopPayload = WhatsAppWebhookHelper::inboundTextPayload('pn_p19', '15550199999', 'STOP', 'wamid.STOP_P19');
        $this->postSignedWebhook($stopPayload, 'p19_secret')->assertStatus(200);
        $this->drainDatabaseQueue();

        $latestConsent = DB::table('whatsapp_consents')
            ->where('organization_id', $org->id)
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($latestConsent);
        $this->assertFalse((bool) $latestConsent->is_opted_in);

        // After STOP: both free-form text and APPROVED template sends must be blocked without calling Meta API
        $this->actingAsInOrganization($admin, $org->id);

        $blockedText = $this->postJson("/ajax/whatsapp/conversations/{$conversationId}/send-text", [
            'body' => 'Ping after STOP',
        ]);
        $this->assertStatusIn($blockedText, [403, 422]);

        $blockedTemplate = $this->postJson("/ajax/whatsapp/conversations/{$conversationId}/send-template", [
            'template_id' => $templateId,
            'parameters' => ['first_name' => 'Alice'],
        ]);
        $this->assertStatusIn($blockedTemplate, [403, 422]);

        $this->assertCount($sentCountBeforeStop, Http::recorded(), 'Opted-out contact must trigger zero Meta Graph API outbound calls.');
    }

    public function test_t3_p20_inbound_whatsapp_on_assigned_contact_deal_dispatches_inbound_notification(): void
    {
        config(['services.meta.app_secret' => 'p20_secret']);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P20 Inbound Notify Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_p20',
            'phone_number_id' => 'pn_p20',
            'display_phone_number' => '+15550200001',
            'access_token_encrypted' => 'enc-p20',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload([
            'phone' => '+15557770020',
        ]))->assertStatus(201))['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');

        $this->postJson('/ajax/deals', [
            'title' => 'Active WA Deal',
            'amount' => 9500,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $payload = WhatsAppWebhookHelper::inboundTextPayload('pn_p20', '15557770020', 'Ready to sign contract!', 'wamid.IN_P20');
        $this->postSignedWebhook($payload, 'p20_secret')->assertStatus(200);
        $this->drainDatabaseQueue();

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('notifications')->where('notifiable_id', $rep->id)->count(),
            'Deal owner must receive InboundWhatsAppNotification on inbound customer WhatsApp message.'
        );

        $notifRes = $this->actingAsInOrganization($rep, $org->id)->getJson('/ajax/notifications')->assertStatus(200);
        $notifJson = $notifRes->json();
        $this->assertGreaterThanOrEqual(1, (int) ($notifJson['unread_count'] ?? count($this->extractJsonData($notifRes))));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload, string $secret, ?string $overrideSignature = null): TestResponse
    {
        $rawJson = WhatsAppWebhookHelper::encode($payload);
        $signature = $overrideSignature ?? WhatsAppWebhookHelper::signPayload($rawJson, $secret);

        return $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $rawJson
        );
    }
}
