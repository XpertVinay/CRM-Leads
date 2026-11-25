<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F19WhatsAppWindowTemplatesConsentBoundaryTest extends E2ETestCase
{
    public function test_t2_f19_01_send_text_blocked_at_24h_plus_1s_and_null_last_inbound_at(): void
    {
        Http::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Window Expiry Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_t2_19_01',
            'phone_number_id' => 'pn_t2_19_01',
            'display_phone_number' => '+15551910001',
            'access_token_encrypted' => 'enc-t2-19-01',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $c1 = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559110001']))->assertStatus(201)
        )['id'];
        $c2 = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559110002']))->assertStatus(201)
        )['id'];

        $convExpired = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $c1,
            'customer_phone' => '+15559110001',
            'last_inbound_at' => now()->subHours(24)->subSecond(),
            'last_message_at' => now()->subHours(24)->subSecond(),
        ]);

        $convNull = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $c2,
            'customer_phone' => '+15559110002',
            'last_inbound_at' => null,
            'last_message_at' => null,
        ]);

        $this->assertFalse($convExpired->isWindowActive());
        $this->assertFalse($convNull->isWindowActive());

        $this->postJson("/ajax/whatsapp/conversations/{$convExpired->id}/send-text", [
            'body' => 'Expired by 1 second',
        ])->assertStatus(422);

        $this->postJson("/ajax/whatsapp/conversations/{$convNull->id}/send-text", [
            'body' => 'Never had inbound',
        ])->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f19_02_inbound_stop_unsubscribe_cancel_opts_out_and_blocks_text_and_template(): void
    {
        config(['services.meta.app_secret' => 'secret_opt_out']);
        $this->enableDatabaseDrivers();
        Http::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'OptOut Keyword Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_optout',
            'phone_number_id' => 'pn_optout',
            'display_phone_number' => '+15551910002',
            'access_token_encrypted' => 'enc-optout',
            'is_active' => true,
        ]);

        $approvedTemplate = $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'approved_optout_check',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Hi {{1}}',
            'parameter_placeholders' => ['first_name'],
            'meta_status' => 'APPROVED',
        ]);

        $keywords = ['STOP', 'unsubscribe', '  CANCEL  '];

        foreach ($keywords as $idx => $keyword) {
            $phone = '+1555912000' . ($idx + 1);
            $this->actingAsInOrganization($admin, $org->id);
            $contactId = (int) $this->extractJsonData(
                $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => $phone]))->assertStatus(201)
            )['id'];

            $this->postJson('/ajax/whatsapp/consents', [
                'contact_id' => $contactId,
                'phone' => $phone,
                'is_opted_in' => true,
                'consent_source' => 'web_form',
            ])->assertStatusIn([200, 201]);

            $conv = $waConvModel::create([
                'organization_id' => $org->id,
                'whatsapp_account_id' => $account->id,
                'contact_id' => $contactId,
                'customer_phone' => $phone,
                'last_inbound_at' => now()->subMinutes(5),
                'last_message_at' => now()->subMinutes(5),
            ]);

            Auth::logout();
            $this->clearTenantHeader()->withSession([]);

            $rawJson = WhatsAppWebhookHelper::encode(
                WhatsAppWebhookHelper::inboundTextPayload('pn_optout', $phone, $keyword, 'wamid.OPT_' . $idx)
            );
            $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($rawJson, 'secret_opt_out'),
            ], $rawJson)->assertStatus(200);
            $this->drainDatabaseQueue();

            $latestConsent = DB::table('whatsapp_consents')
                ->where('organization_id', $org->id)
                ->where('contact_id', $contactId)
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($latestConsent);
            $this->assertFalse((bool) $latestConsent->is_opted_in, "Keyword '{$keyword}' must opt out contact.");

            $this->actingAsInOrganization($admin, $org->id);

            $textRes = $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-text", [
                'body' => 'Should be blocked after opt-out',
            ]);
            $this->assertStatusIn($textRes, [403, 422]);

            $tplRes = $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-template", [
                'template_id' => $approvedTemplate->id,
                'template_name' => $approvedTemplate->name,
                'parameters' => ['User'],
            ]);
            $this->assertStatusIn($tplRes, [403, 422]);
        }

        Http::assertNothingSent();
    }

    public function test_t2_f19_03_pending_or_rejected_template_is_rejected_with_422(): void
    {
        Http::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Unapproved Tpl Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_unapp',
            'phone_number_id' => 'pn_unapp',
            'display_phone_number' => '+15551910003',
            'access_token_encrypted' => 'enc-unapp',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559130003']))->assertStatus(201)
        )['id'];

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559130003',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
        ])->assertStatusIn([200, 201]);

        $conv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559130003',
            'last_inbound_at' => now()->subHours(48),
            'last_message_at' => now()->subHours(48),
        ]);

        $pending = $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'tpl_pending',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Pending body',
            'meta_status' => 'PENDING',
        ]);

        $rejected = $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'tpl_rejected',
            'language' => 'en_US',
            'category' => 'marketing',
            'header_type' => 'none',
            'body_text' => 'Rejected body',
            'meta_status' => 'REJECTED',
        ]);

        $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-template", [
            'template_id' => $pending->id,
            'template_name' => $pending->name,
        ])->assertStatus(422);

        $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-template", [
            'template_id' => $rejected->id,
            'template_name' => $rejected->name,
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_t2_f19_04_read_only_user_blocked_from_sending_text_template_and_consent(): void
    {
        Http::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'ReadOnly WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_ro_19',
            'phone_number_id' => 'pn_ro_19',
            'display_phone_number' => '+15551910004',
            'access_token_encrypted' => 'enc-ro-19',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559140004']))->assertStatus(201)
        )['id'];

        $conv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559140004',
            'last_inbound_at' => now()->subMinutes(5),
            'last_message_at' => now()->subMinutes(5),
        ]);

        $tpl = $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'ro_approved_tpl',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Approved text',
            'meta_status' => 'APPROVED',
        ]);

        $this->actingAsInOrganization($readOnly, $org->id);

        $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-text", [
            'body' => 'ReadOnly blocked',
        ])->assertStatus(403);

        $this->postJson("/ajax/whatsapp/conversations/{$conv->id}/send-template", [
            'template_id' => $tpl->id,
            'template_name' => $tpl->name,
        ])->assertStatus(403);

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559140004',
            'is_opted_in' => true,
            'consent_source' => 'manual',
        ])->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_t2_f19_05_cross_organization_conversation_access_and_send_return_404_or_403(): void
    {
        Http::fake();

        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'CrossConv Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'CrossConv Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';

        $accountB = $waAccountModel::create([
            'organization_id' => $orgB->id,
            'waba_id' => 'waba_cross_b',
            'phone_number_id' => 'pn_cross_b',
            'display_phone_number' => '+15551910005',
            'access_token_encrypted' => 'enc-cross-b',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($adminB, $orgB->id);
        $contactBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559150005']))->assertStatus(201)
        )['id'];

        $convB = $waConvModel::create([
            'organization_id' => $orgB->id,
            'whatsapp_account_id' => $accountB->id,
            'contact_id' => $contactBId,
            'customer_phone' => '+15559150005',
            'last_inbound_at' => now()->subMinutes(5),
            'last_message_at' => now()->subMinutes(5),
        ]);

        $tplB = $waTplModel::create([
            'whatsapp_account_id' => $accountB->id,
            'name' => 'cross_b_tpl',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Org B template',
            'meta_status' => 'APPROVED',
        ]);

        $this->actingAsInOrganization($adminA, $orgA->id);

        $this->assertStatusIn($this->getJson("/ajax/whatsapp/conversations/{$convB->id}/messages"), [403, 404]);
        $this->assertStatusIn($this->postJson("/ajax/whatsapp/conversations/{$convB->id}/send-text", ['body' => 'Hijack']), [403, 404]);
        $this->assertStatusIn($this->postJson("/ajax/whatsapp/conversations/{$convB->id}/send-template", [
            'template_id' => $tplB->id,
            'template_name' => $tplB->name,
        ]), [403, 404]);

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $orgB->id)->count());
    }
}
