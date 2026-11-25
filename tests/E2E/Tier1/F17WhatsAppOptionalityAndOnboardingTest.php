<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;

class F17WhatsAppOptionalityAndOnboardingTest extends E2ETestCase
{
    public function test_t1_f17_01_organization_without_whatsapp_runs_full_core_crm_cleanly(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'NoWA Core Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        if (Schema::hasTable('whatsapp_accounts')) {
            $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $org->id)->count());
        }

        $linkRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'NoWA Campaign',
            'code' => 'nowa-promo-' . strtolower(substr(md5((string) $org->id), 0, 6)),
            'destination_url' => 'https://example.test/pricing',
            'channel' => 'ad',
            'ad_spend' => 250,
        ]);
        $this->assertStatusIn($linkRes, [200, 201]);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'NoWA Client Corp',
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'won')
            ->value('id');

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'NoWA Enterprise Deal',
            'deal_amount' => 15000,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $dealId = (int) DB::table('deals')->where('organization_id', $org->id)->value('id');
        $this->assertGreaterThan(0, $dealId);

        $activityRes = $this->postJson('/ajax/activities', [
            'subject_type' => 'deal',
            'subject_id' => $dealId,
            'type' => 'call',
            'description' => 'Discovery call without WhatsApp configured.',
        ]);
        $this->assertStatusIn($activityRes, [200, 201]);

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $execHtml = $this->get('/analytics/executive');
        $execHtml->assertStatus(200);
        $this->assertNoViteAssets($execHtml);

        $this->getJson('/ajax/analytics/executive')->assertStatus(200);

        if (Schema::hasTable('whatsapp_accounts')) {
            $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $org->id)->count());
        }
    }

    public function test_t1_f17_02_org_admin_onboards_whatsapp_via_embedded_signup_oauth_code(): void
    {
        config([
            'services.meta.app_id' => 'meta_app_123',
            'services.meta.app_secret' => 'meta_secret_123',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
            'services.meta.webhook_verify_token' => 'verify_tok_123',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'access_token' => 'EAAG_embedded_signup_token_001',
                    'token_type' => 'bearer',
                ], 200)
                ->push([
                    'id' => 'pn_embedded_001',
                    'display_phone_number' => '+15551700101',
                    'verified_name' => 'Acme Support',
                    'quality_rating' => 'GREEN',
                ], 200)
                ->push([
                    'success' => true,
                    'data' => [],
                ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Embedded WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $response = $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/whatsapp/connect', [
                'code' => 'meta_oauth_code_abc123',
                'waba_id' => 'waba_embedded_001',
                'phone_number_id' => 'pn_embedded_001',
                'display_phone_number' => '+15551700101',
                'is_coexistence_enabled' => true,
            ]);

        $this->assertStatusIn($response, [200, 201]);
        Http::assertSentCount(1);

        $account = DB::table('whatsapp_accounts')->where('organization_id', $org->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('waba_embedded_001', $account->waba_id);
        $this->assertSame('pn_embedded_001', $account->phone_number_id);
        $this->assertSame('connected', $account->status);
        $this->assertTrue((bool) $account->is_active);
        $this->assertTrue((bool) $account->is_coexistence_enabled);
    }

    public function test_t1_f17_03_org_admin_onboards_manual_waba_and_stores_encrypted_access_token(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Manual WABA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $plainToken = 'EAAG_manual_plain_system_token_999';

        $response = $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_manual_001',
                'phone_number_id' => 'pn_manual_001',
                'display_phone_number' => '+15551700202',
                'verified_name' => 'Manual Biz',
                'access_token' => $plainToken,
                'webhook_verify_token' => 'manual_verify_secret',
                'is_coexistence_enabled' => false,
            ]);

        $this->assertStatusIn($response, [200, 201]);

        $account = DB::table('whatsapp_accounts')->where('organization_id', $org->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('waba_manual_001', $account->waba_id);
        $this->assertSame('pn_manual_001', $account->phone_number_id);

        $rawCiphertext = (string) ($account->access_token_encrypted ?? '');
        $this->assertNotSame('', $rawCiphertext);
        $this->assertNotSame($plainToken, $rawCiphertext);
        $this->assertStringNotContainsString($plainToken, $rawCiphertext);

        $decrypted = Crypt::decryptString($rawCiphertext);
        $this->assertSame($plainToken, $decrypted);
    }

    public function test_t1_f17_04_org_admin_syncs_whatsapp_templates_from_meta_graph_api(): void
    {
        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'name' => 'welcome_follow_up',
                        'language' => 'en_US',
                        'category' => 'UTILITY',
                        'status' => 'APPROVED',
                        'components' => [
                            [
                                'type' => 'BODY',
                                'text' => 'Hello {{1}}, thanks for contacting {{2}}.',
                            ],
                        ],
                    ],
                    [
                        'name' => 'promo_draft',
                        'language' => 'en_US',
                        'category' => 'MARKETING',
                        'status' => 'PENDING',
                        'components' => [
                            [
                                'type' => 'BODY',
                                'text' => 'Special offer available!',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Template Sync Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_sync_001',
                'phone_number_id' => 'pn_sync_001',
                'display_phone_number' => '+15551700303',
                'access_token' => 'EAAG_sync_token',
            ])
            ->assertStatusIn([200, 201]);

        $syncRes = $this->postJson('/ajax/whatsapp/sync-templates');
        $syncRes->assertStatus(200);

        $accountId = (int) DB::table('whatsapp_accounts')->where('organization_id', $org->id)->value('id');
        $templates = DB::table('whatsapp_templates')
            ->where('whatsapp_account_id', $accountId)
            ->get()
            ->keyBy('name');

        $this->assertTrue($templates->has('welcome_follow_up'));
        $this->assertSame('APPROVED', $templates->get('welcome_follow_up')->meta_status);
        $this->assertTrue($templates->has('promo_draft'));
        $this->assertSame('PENDING', $templates->get('promo_draft')->meta_status);
    }

    public function test_t1_f17_05_org_admin_disconnects_whatsapp_account_and_settings_page_renders(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Disconnect WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $settingsPage = $this->get('/settings/whatsapp');
        $settingsPage->assertStatus(200);
        $this->assertNoViteAssets($settingsPage);

        $this->postJson('/ajax/whatsapp/connect', [
            'waba_id' => 'waba_disc_001',
            'phone_number_id' => 'pn_disc_001',
            'display_phone_number' => '+15551700404',
            'access_token' => 'EAAG_disc_token',
        ])->assertStatusIn([200, 201]);

        $disconnectRes = $this->postJson('/ajax/whatsapp/disconnect');
        if ($disconnectRes->getStatusCode() === 405) {
            $disconnectRes = $this->deleteJson('/ajax/whatsapp/disconnect');
        }
        $disconnectRes->assertStatus(200);

        $account = DB::table('whatsapp_accounts')->where('organization_id', $org->id)->first();
        $this->assertNotNull($account);
        $this->assertTrue(
            $account->status === 'disconnected' || ! (bool) $account->is_active,
            'Disconnecting WhatsApp must mark account disconnected or inactive.'
        );

        $this->get('/leads')->assertStatus(200);
        $this->get('/deals')->assertStatus(200);
    }
}
