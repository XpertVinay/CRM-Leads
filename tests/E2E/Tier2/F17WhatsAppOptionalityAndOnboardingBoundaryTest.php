<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\E2E\Support\E2ETestCase;

class F17WhatsAppOptionalityAndOnboardingBoundaryTest extends E2ETestCase
{
    public function test_t2_f17_01_non_org_admin_roles_blocked_from_whatsapp_settings_and_onboarding(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'WA RBAC Boundary Org']);
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        foreach ([$manager, $rep, $readOnly] as $actor) {
            $this->actingAsInOrganization($actor, $org->id);

            $this->get('/settings/whatsapp')->assertStatus(403);
            $this->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_unauth',
                'phone_number_id' => 'pn_unauth',
                'display_phone_number' => '+15551709999',
                'access_token' => 'EAAG_unauth',
            ])->assertStatus(403);
            $this->postJson('/ajax/whatsapp/sync-templates')->assertStatus(403);
            $disc = $this->postJson('/ajax/whatsapp/disconnect');
            if ($disc->getStatusCode() === 405) {
                $disc = $this->deleteJson('/ajax/whatsapp/disconnect');
            }
            $disc->assertStatus(403);
        }

        $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f17_02_duplicate_phone_number_id_across_organizations_is_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Dup Phone Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Dup Phone Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_dup_a',
                'phone_number_id' => 'pn_shared_unique_999',
                'display_phone_number' => '+15551701111',
                'access_token' => 'EAAG_token_a',
            ])
            ->assertStatusIn([200, 201]);

        $dupRes = $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_dup_b',
                'phone_number_id' => 'pn_shared_unique_999',
                'display_phone_number' => '+15551702222',
                'access_token' => 'EAAG_token_b',
            ]);

        $dupRes->assertStatus(422);
        $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $orgB->id)->count());
    }

    public function test_t2_f17_03_meta_graph_api_400_error_returns_graceful_422_or_502_without_persisting(): void
    {
        config([
            'services.meta.app_id' => 'meta_app_err',
            'services.meta.app_secret' => 'meta_secret_err',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth verification code.',
                    'type' => 'OAuthException',
                    'code' => 100,
                ],
            ], 400),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Meta API Error Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $response = $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/whatsapp/connect', [
                'code' => 'expired_or_invalid_code',
                'waba_id' => 'waba_err_001',
                'phone_number_id' => 'pn_err_001',
                'display_phone_number' => '+15551703333',
            ]);

        $this->assertStatusIn($response, [422, 502]);
        $this->assertSame(0, DB::table('whatsapp_accounts')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f17_04_unconfigured_whatsapp_inbox_and_settings_render_safely(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Unconfigured WA UI Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $settingsRes = $this->get('/settings/whatsapp');
        $settingsRes->assertStatus(200);
        $this->assertNoViteAssets($settingsRes);

        $adminInbox = $this->get('/whatsapp');
        $adminInbox->assertStatus(200);
        $this->assertNoViteAssets($adminInbox);

        $this->actingAsInOrganization($rep, $org->id);
        $repInbox = $this->get('/whatsapp');
        $repInbox->assertStatus(200);
        $this->assertNoViteAssets($repInbox);

        $convRes = $this->getJson('/ajax/whatsapp/conversations');
        $convRes->assertStatus(200);
        $this->assertSame([], $this->extractJsonData($convRes));
    }

    public function test_t2_f17_05_missing_credentials_rejected_and_access_token_never_exposed_in_responses(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Secret Redaction Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/whatsapp/connect', [])->assertStatus(422);

        $plainSecret = 'EAAG_super_secret_do_not_expose_777';
        $connectRes = $this->postJson('/ajax/whatsapp/connect', [
            'waba_id' => 'waba_sec_001',
            'phone_number_id' => 'pn_sec_001',
            'display_phone_number' => '+15551704444',
            'access_token' => $plainSecret,
        ]);
        $this->assertStatusIn($connectRes, [200, 201]);
        $this->assertStringNotContainsString($plainSecret, (string) $connectRes->getContent());

        $settingsHtml = $this->get('/settings/whatsapp');
        $settingsHtml->assertStatus(200);
        $this->assertStringNotContainsString($plainSecret, (string) $settingsHtml->getContent());

        $stored = (string) DB::table('whatsapp_accounts')
            ->where('organization_id', $org->id)
            ->value('access_token_encrypted');
        $this->assertNotSame($plainSecret, $stored);
        $this->assertStringNotContainsString($plainSecret, $stored);
    }
}
