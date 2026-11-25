<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F18WhatsAppWebhookAndCoexistenceTest extends E2ETestCase
{
    public function test_t1_f18_01_get_webhook_verification_challenge_returns_raw_challenge(): void
    {
        config(['services.meta.webhook_verify_token' => 'verify_token_f18']);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $response = $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify_token_f18&hub.challenge=CHALLENGE_F18_999');

        $response->assertStatus(200);
        $this->assertSame('CHALLENGE_F18_999', (string) $response->getContent());
    }

    public function test_t1_f18_02_signed_post_webhook_queues_job_on_database_queue_and_acks_200(): void
    {
        config(['services.meta.app_secret' => 'secret_f18_02']);
        $this->enableDatabaseDrivers();

        $org = $this->registerOrganizationAndAdmin(['organization_name' => 'Webhook Queue Org'])['organization'];
        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f18_02',
            'phone_number_id' => 'pn_f18_02',
            'display_phone_number' => '+15551800002',
            'access_token_encrypted' => 'enc-token-02',
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);
        $this->clearTenantContextIfBound();

        $rawJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_f18_02', '+15559000002', 'Queue check', 'wamid.Q1802')
        );
        $sig = WhatsAppWebhookHelper::signPayload($rawJson, 'secret_f18_02');

        $response = $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $sig,
            ],
            $rawJson
        );

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $org->id)->count());

        $this->drainDatabaseQueue();

        $this->assertSame(1, DB::table('whatsapp_messages')->where('organization_id', $org->id)->count());

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->assertFalse($this->app->make($tenantContextClass)->hasTenant());
        }
    }

    public function test_t1_f18_03_inbound_message_creates_contact_and_conversation_and_sets_last_inbound_at(): void
    {
        config(['services.meta.app_secret' => 'secret_f18_03']);
        $this->enableDatabaseDrivers();

        $org = $this->registerOrganizationAndAdmin(['organization_name' => 'Inbound WA Org'])['organization'];
        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';

        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f18_03',
            'phone_number_id' => 'pn_f18_03',
            'display_phone_number' => '+15551800003',
            'access_token_encrypted' => 'enc-token-03',
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $rawJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload(
                'pn_f18_03',
                '+15559000003',
                'Hello, I want a demo.',
                'wamid.INB1803',
                ['contact_name' => 'Jordan Prospect']
            )
        );
        $sig = WhatsAppWebhookHelper::signPayload($rawJson, 'secret_f18_03');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
        ], $rawJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $contact = DB::table('contacts')->where('organization_id', $org->id)->first();
        $this->assertNotNull($contact);
        $this->assertTrue(in_array($contact->phone, ['+15559000003', '15559000003'], true));

        $conv = DB::table('whatsapp_conversations')
            ->where('organization_id', $org->id)
            ->where('contact_id', $contact->id)
            ->first();
        $this->assertNotNull($conv);
        $this->assertNotNull($conv->last_inbound_at);
        $this->assertNotNull($conv->last_message_at);

        $msg = DB::table('whatsapp_messages')
            ->where('organization_id', $org->id)
            ->where('whatsapp_conversation_id', $conv->id)
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('inbound', $msg->direction);
        $this->assertSame('wamid.INB1803', $msg->meta_message_id);
        $this->assertSame('Hello, I want a demo.', $msg->body);
    }

    public function test_t1_f18_04_coexistence_smb_message_echo_and_status_updates_reconcile_cleanly(): void
    {
        config(['services.meta.app_secret' => 'secret_f18_04']);
        $this->enableDatabaseDrivers();

        $org = $this->registerOrganizationAndAdmin(['organization_name' => 'Coexistence Org'])['organization'];
        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';

        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f18_04',
            'phone_number_id' => 'pn_f18_04',
            'display_phone_number' => '+15551800004',
            'access_token_encrypted' => 'enc-token-04',
            'is_coexistence_enabled' => true,
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $echoJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::coexistenceEchoPayload(
                'pn_f18_04',
                '+15559000004',
                'Replied from WhatsApp Business mobile app',
                'wamid.ECHO1804',
                ['business_phone' => '15551800004']
            )
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($echoJson, 'secret_f18_04'),
        ], $echoJson)->assertStatus(200);
        $this->drainDatabaseQueue();

        $msg = DB::table('whatsapp_messages')
            ->where('organization_id', $org->id)
            ->where('meta_message_id', 'wamid.ECHO1804')
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('outbound', $msg->direction);
        $this->assertTrue((bool) $msg->is_coexistence_echo);

        $deliveredJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::statusUpdatePayload('pn_f18_04', '+15559000004', 'wamid.ECHO1804', 'delivered')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($deliveredJson, 'secret_f18_04'),
        ], $deliveredJson)->assertStatus(200);
        $this->drainDatabaseQueue();

        $this->assertSame('delivered', DB::table('whatsapp_messages')->where('id', $msg->id)->value('status'));

        $readJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::statusUpdatePayload('pn_f18_04', '+15559000004', 'wamid.ECHO1804', 'read')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($readJson, 'secret_f18_04'),
        ], $readJson)->assertStatus(200);
        $this->drainDatabaseQueue();

        $this->assertSame('read', DB::table('whatsapp_messages')->where('id', $msg->id)->value('status'));
    }

    public function test_t1_f18_05_verified_two_way_exchange_records_conversation_qualified_touchpoint(): void
    {
        config([
            'services.meta.app_secret' => 'secret_f18_05',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);
        $this->enableDatabaseDrivers();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.OUT1805']],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Qualified Conv Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/whatsapp/connect', [
            'waba_id' => 'waba_f18_05',
            'phone_number_id' => 'pn_f18_05',
            'display_phone_number' => '+15551800005',
            'access_token' => 'EAAG_f18_05_token',
            'is_coexistence_enabled' => true,
        ])->assertStatusIn([200, 201]);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'WA Click to Chat',
            'code' => 'wa-chat-1805',
            'destination_url' => 'https://wa.me/15551800005?text=Hi',
            'channel' => 'whatsapp',
        ])->assertStatusIn([200, 201]);

        $clickRes = $this->get('/l/wa-chat-1805');
        $clickRes->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->orderByDesc('id')->value('click_token');
        $this->assertNotSame('', $clickToken);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'phone' => '+15559000005',
            'click_token' => $clickToken,
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => false,
        ])->assertStatusIn([200, 201]);

        $this->assertSame(
            0,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('event_milestone', 'conversation_qualified')
                ->count(),
            'Clicking wa.me link alone must never create conversation_qualified.'
        );

        // Inbound message from prospect (1-way)
        $inboundJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_f18_05', '+15559000005', 'Hi from wa.me link', 'wamid.INB1805')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($inboundJson, 'secret_f18_05'),
        ], $inboundJson)->assertStatus(200);
        $this->drainDatabaseQueue();

        $convId = (int) DB::table('whatsapp_conversations')->where('organization_id', $org->id)->value('id');
        $this->assertGreaterThan(0, $convId);

        // Outbound reply completes verified 2-way exchange
        $this->actingAsInOrganization($admin, $org->id)
            ->postJson("/ajax/whatsapp/conversations/{$convId}/send-text", [
                'body' => 'Thanks for reaching out! How can we help?',
            ])
            ->assertStatusIn([200, 201]);

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('event_milestone', 'conversation_qualified')
                ->count()
        );
    }
}
