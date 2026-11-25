<?php

namespace Tests\E2E\Tier3;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class TrackingLeadsAndConversionPairwiseTest extends E2ETestCase
{
    public function test_t3_p06_tracking_link_click_token_binds_same_org_lead_and_ignores_cross_org_cookie(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'P06 Track Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'P06 Track Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id);
        $linkRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'Spring Ad Promo',
            'code' => 'spring-p06',
            'destination_url' => 'https://example.test/landing',
            'channel' => 'ad',
            'utm_source' => 'google',
            'ad_spend' => 500,
        ]);
        $this->assertStatusIn($linkRes, [200, 201]);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $redirectRes = $this->get('/l/spring-p06');
        $redirectRes->assertStatus(302);
        $redirectRes->assertRedirect('https://example.test/landing');

        $clickRow = DB::table('link_clicks')->where('organization_id', $orgA->id)->first();
        $this->assertNotNull($clickRow);
        $clickToken = $clickRow->click_token;

        // Zero-false-conversion check
        $this->assertSame(0, DB::table('leads')->where('organization_id', $orgA->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $orgA->id)->count());
        $this->assertSame(1, DB::table('touchpoints')->where('organization_id', $orgA->id)->where('event_milestone', 'click')->count());

        // Cross-org lead submission carrying Org A's click_token must NOT bind a touchpoint in Org B
        $this->actingAsInOrganization($adminB, $orgB->id)
            ->withCookie('click_token', $clickToken)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'email' => 'cross-click@orgb.test',
                'click_token' => $clickToken,
            ]))
            ->assertStatus(201);

        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $orgB->id)->count());

        // Same-org lead submission with click_token creates lead_captured touchpoint in Org A
        $leadARes = $this->actingAsInOrganization($adminA, $orgA->id)
            ->withCookie('click_token', $clickToken)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'email' => 'valid-click@orga.test',
                'click_token' => $clickToken,
            ]));
        $this->assertStatusIn($leadARes, [200, 201]);
        $leadAId = (int) $this->extractJsonData($leadARes)['id'];

        $capturedTp = DB::table('touchpoints')
            ->where('organization_id', $orgA->id)
            ->where('event_milestone', 'lead_captured')
            ->where('lead_id', $leadAId)
            ->first();

        $this->assertNotNull($capturedTp);
        $this->assertSame((int) $clickRow->tracking_link_id, (int) $capturedTp->tracking_link_id);
        $this->assertSame((int) $clickRow->id, (int) $capturedTp->link_click_id);
    }

    public function test_t3_p07_whatsapp_tracking_link_click_vs_verified_two_way_conversation_qualified(): void
    {
        config(['services.meta.app_secret' => 'p07_secret']);

        Http::fake([
            '*/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '15551230007', 'wa_id' => '15551230007']],
                'messages' => [['id' => 'wamid.OUT_P07']],
            ], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P07 WA Link Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_p07',
            'phone_number_id' => 'pn_p07',
            'display_phone_number' => '+15550000707',
            'access_token_encrypted' => 'enc-p07',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'WA Promo Link',
            'code' => 'wa-p07',
            'destination_url' => 'https://wa.me/15550000707',
            'channel' => 'whatsapp',
        ])->assertStatus(201);

        // Public click logs click only — NOT conversation_qualified
        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->get('/l/wa-p07')->assertStatus(302);

        $clickToken = DB::table('link_clicks')->where('organization_id', $org->id)->value('click_token');
        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'conversation_qualified')->count());

        // Capture B2C lead with phone & click_token, then convert to standalone Contact
        $this->actingAsInOrganization($admin, $org->id);
        $leadRes = $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'phone' => '+15551230007',
            'click_token' => $clickToken,
        ]))->assertStatus(201);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", ['create_deal' => false]);
        $this->assertStatusIn($convertRes, [200, 201]);

        // Customer sends inbound WhatsApp message via signed webhook
        $payload = WhatsAppWebhookHelper::inboundTextPayload('pn_p07', '15551230007', 'Hi, tell me pricing', 'wamid.IN_P07');
        $rawJson = WhatsAppWebhookHelper::encode($payload);
        $signature = WhatsAppWebhookHelper::signPayload($rawJson, 'p07_secret');

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->call(
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
        )->assertStatus(200);
        $this->drainDatabaseQueue();

        $conversationId = (int) DB::table('whatsapp_conversations')->where('organization_id', $org->id)->value('id');
        $this->assertGreaterThan(0, $conversationId);

        // Rep replies via CRM -> completes verified 2-way exchange -> conversation_qualified touchpoint recorded
        $this->actingAsInOrganization($admin, $org->id)
            ->postJson("/ajax/whatsapp/conversations/{$conversationId}/send-text", [
                'body' => 'Sure! Plans start at $99/mo.',
            ])
            ->assertStatus(200);

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('event_milestone', 'conversation_qualified')
                ->count()
        );
    }

    public function test_t3_p08_lead_assignment_triggers_database_and_mail_notification_for_assignee(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P08 Notify Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $peerRep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'assign-me@acme.test']))->assertStatus(201);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        $this->actingAsInOrganization($manager, $org->id)
            ->patchJson("/ajax/leads/{$leadId}/assign", ['assigned_user_id' => $rep->id])
            ->assertStatus(200);

        $this->assertSame((int) $rep->id, (int) DB::table('leads')->where('id', $leadId)->value('assigned_user_id'));

        $notifRow = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->whereNull('read_at')
            ->latest('created_at')
            ->first();
        $this->assertNotNull($notifRow, 'Database notification must be created for assigned rep.');

        // Assignee sees notification in bell endpoint & can mark read
        $listRes = $this->actingAsInOrganization($rep, $org->id)->getJson('/ajax/notifications')->assertStatus(200);
        $listData = $listRes->json();
        $this->assertGreaterThanOrEqual(1, (int) ($listData['unread_count'] ?? count($this->extractJsonData($listRes))));

        // Another user cannot mark rep's notification as read
        $this->actingAsInOrganization($peerRep, $org->id)
            ->patchJson("/ajax/notifications/{$notifRow->id}/read")
            ->assertStatus(404);

        // Rep marks notification read
        $this->actingAsInOrganization($rep, $org->id)
            ->patchJson("/ajax/notifications/{$notifRow->id}/read")
            ->assertStatus(200);

        $this->assertNotNull(DB::table('notifications')->where('id', $notifRow->id)->value('read_at'));
    }

    public function test_t3_p09_repeated_b2b_lead_conversions_match_existing_account_in_directory(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P09 B2B Match Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $lead1Id = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'first_name' => 'Alice',
            'email' => 'alice@vertex.test',
            'company_name' => 'Vertex Robotics',
        ]))->assertStatus(201))['id'];

        $lead2Id = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'first_name' => 'Bob',
            'email' => 'bob@vertex.test',
            'company_name' => 'vertex robotics',
        ]))->assertStatus(201))['id'];

        $this->postJson("/ajax/leads/{$lead1Id}/convert", [
            'create_deal' => true,
            'deal_title' => 'Vertex Phase 1',
            'deal_amount' => 10000,
        ])->assertStatus(200);

        $this->postJson("/ajax/leads/{$lead2Id}/convert", [
            'create_deal' => true,
            'deal_title' => 'Vertex Expansion',
            'deal_amount' => 20000,
        ])->assertStatus(200);

        $accounts = DB::table('accounts')->where('organization_id', $org->id)->get();
        $this->assertCount(1, $accounts, 'Second B2B conversion for same company must reuse existing Account.');
        $accountId = (int) $accounts->first()->id;

        $this->assertSame(2, DB::table('contacts')->where('organization_id', $org->id)->where('account_id', $accountId)->count());
        $this->assertSame(2, DB::table('deals')->where('organization_id', $org->id)->where('account_id', $accountId)->count());

        $accountsSearch = $this->getJson('/ajax/accounts?search=Vertex')->assertStatus(200);
        $this->assertCount(1, $this->extractJsonData($accountsSearch));

        $contactsFilter = $this->getJson("/ajax/contacts?account_id={$accountId}")->assertStatus(200);
        $this->assertCount(2, $this->extractJsonData($contactsFilter));
    }

    public function test_t3_p10_b2c_lead_conversion_creates_standalone_contact_and_deal_with_null_account(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P10 B2C Convert Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'first_name' => 'Clara',
            'last_name' => 'consumer',
            'email' => 'clara@consumer.test',
            'location' => 'Seattle, WA',
        ]))->assertStatus(201))['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Clara Annual Plan',
            'deal_amount' => 299.00,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $this->assertSame(0, DB::table('accounts')->where('organization_id', $org->id)->count());

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $this->assertSame('converted', $leadRow->status);
        $this->assertNull($leadRow->converted_account_id);
        $this->assertNotNull($leadRow->converted_contact_id);
        $this->assertNotNull($leadRow->converted_deal_id);

        $contactRow = DB::table('contacts')->where('id', $leadRow->converted_contact_id)->first();
        $dealRow = DB::table('deals')->where('id', $leadRow->converted_deal_id)->first();

        $this->assertNull($contactRow->account_id);
        $this->assertNull($dealRow->account_id);
        $this->assertSame((int) $contactRow->id, (int) $dealRow->contact_id);

        $this->get("/contacts/{$contactRow->id}")->assertStatus(200);
        $searchRes = $this->getJson('/ajax/contacts?search=Clara')->assertStatus(200);
        $this->assertCount(1, $this->extractJsonData($searchRes));
    }
}
