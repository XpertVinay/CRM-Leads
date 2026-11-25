<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\E2E\Support\E2ETestCase;

class F19WhatsAppWindowTemplatesConsentTest extends E2ETestCase
{
    public function test_t1_f19_01_php_is_window_active_and_send_text_succeeds_within_24_hours(): void
    {
        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.TEXT1901']],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Window Active Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $this->assertTrue(class_exists($waAccountModel), 'WhatsAppAccount model must exist.');
        $this->assertTrue(class_exists($waConvModel), 'WhatsAppConversation model must exist.');

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f19_01',
            'phone_number_id' => 'pn_f19_01',
            'display_phone_number' => '+15551900001',
            'access_token_encrypted' => 'enc-f19-01',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559100001']))->assertStatus(201)
        )['id'];

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559100001',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
        ])->assertStatusIn([200, 201]);

        $conversation = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559100001',
            'last_inbound_at' => now()->subHours(23)->subMinutes(50),
            'last_message_at' => now()->subHours(23)->subMinutes(50),
        ]);

        $this->assertTrue($conversation->isWindowActive());

        $listRes = $this->getJson('/ajax/whatsapp/conversations');
        $listRes->assertStatus(200);
        $items = $this->extractJsonData($listRes);
        $this->assertNotEmpty($items);
        $this->assertTrue((bool) ($items[0]['is_window_active'] ?? false));

        $sendRes = $this->postJson("/ajax/whatsapp/conversations/{$conversation->id}/send-text", [
            'body' => 'Hello inside the 24-hour window!',
        ]);
        $this->assertStatusIn($sendRes, [200, 201]);
        Http::assertSentCount(1);

        $msg = DB::table('whatsapp_messages')
            ->where('organization_id', $org->id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('outbound', $msg->direction);
        $this->assertSame('text', $msg->message_type);
        $this->assertSame('wamid.TEXT1901', $msg->meta_message_id);
    }

    public function test_t1_f19_02_send_approved_template_succeeds_outside_24_hour_window(): void
    {
        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);
        $this->enableDatabaseDrivers();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.TPL1902']],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Template Send Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';
        $this->assertTrue(class_exists($waTplModel), 'WhatsAppTemplate model must exist.');

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f19_02',
            'phone_number_id' => 'pn_f19_02',
            'display_phone_number' => '+15551900002',
            'access_token_encrypted' => 'enc-f19-02',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559100002']))->assertStatus(201)
        )['id'];

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559100002',
            'is_opted_in' => true,
            'consent_source' => 'checkout_checkbox',
        ])->assertStatusIn([200, 201]);

        $conversation = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559100002',
            'last_inbound_at' => now()->subHours(30),
            'last_message_at' => now()->subHours(30),
        ]);

        $this->assertFalse($conversation->isWindowActive());

        $template = $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'appointment_reminder',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Hi {{1}}, your appointment is confirmed.',
            'parameter_placeholders' => ['first_name'],
            'meta_status' => 'APPROVED',
        ]);

        $res = $this->postJson("/ajax/whatsapp/conversations/{$conversation->id}/send-template", [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'parameters' => ['Alice'],
        ]);

        $this->assertStatusIn($res, [200, 201]);
        $this->drainDatabaseQueue();

        Http::assertSentCount(1);

        $msg = DB::table('whatsapp_messages')
            ->where('organization_id', $org->id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('message_type', 'template')
            ->first();
        $this->assertNotNull($msg);
        $this->assertSame('outbound', $msg->direction);
        $this->assertSame('appointment_reminder', $msg->template_name);
    }

    public function test_t1_f19_03_explicit_opt_in_consent_recorded_and_persisted(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Consent Ledger Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559100003']))->assertStatus(201)
        )['id'];

        $res = $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559100003',
            'is_opted_in' => true,
            'consent_source' => 'checkout_checkbox',
        ]);
        $this->assertStatusIn($res, [200, 201]);

        $consent = DB::table('whatsapp_consents')
            ->where('organization_id', $org->id)
            ->where('contact_id', $contactId)
            ->first();
        $this->assertNotNull($consent);
        $this->assertTrue((bool) $consent->is_opted_in);
        $this->assertSame('checkout_checkbox', $consent->consent_source);
        $this->assertNotNull($consent->consent_timestamp);
    }

    public function test_t1_f19_04_livewire_whatsapp_chat_widget_renders_and_dispatches_message(): void
    {
        $this->assertTrue($this->hasLivewireInstalled(), 'Livewire 3 must be installed.');

        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.LWCHAT1904']],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Chat Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f19_04',
            'phone_number_id' => 'pn_f19_04',
            'display_phone_number' => '+15551900004',
            'access_token_encrypted' => 'enc-f19-04',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['first_name' => 'ChatCustomer', 'phone' => '+15559100004']))->assertStatus(201)
        )['id'];

        $this->postJson('/ajax/whatsapp/consents', [
            'contact_id' => $contactId,
            'phone' => '+15559100004',
            'is_opted_in' => true,
            'consent_source' => 'inbound_message',
        ])->assertStatusIn([200, 201]);

        $conversation = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559100004',
            'last_inbound_at' => now()->subMinutes(10),
            'last_message_at' => now()->subMinutes(10),
        ]);

        $inboxPage = $this->get('/whatsapp');
        $inboxPage->assertStatus(200);
        $this->assertNoViteAssets($inboxPage);

        $widgetClass = $this->livewireClassIfExists(
            'App\\Modules\\Communications\\WhatsApp\\Livewire\\WhatsAppChatWidget'
        );
        if ($widgetClass === null) {
            $this->fail('WhatsAppChatWidget Livewire component must exist.');
        }

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        $component = Livewire::actingAs($admin)->test($widgetClass, ['conversationId' => $conversation->id]);
        $component->assertStatus(200);
        $component->assertSee('+15559100004');
    }

    public function test_t1_f19_05_livewire_template_selector_renders_approved_templates_only(): void
    {
        $this->assertTrue($this->hasLivewireInstalled(), 'Livewire 3 must be installed.');

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Template Selector Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waTplModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppTemplate';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_f19_05',
            'phone_number_id' => 'pn_f19_05',
            'display_phone_number' => '+15551900005',
            'access_token_encrypted' => 'enc-f19-05',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559100005']))->assertStatus(201)
        )['id'];

        $conversation = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559100005',
            'last_inbound_at' => now()->subHours(36),
            'last_message_at' => now()->subHours(36),
        ]);

        $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'approved_reengage_tpl',
            'language' => 'en_US',
            'category' => 'utility',
            'header_type' => 'none',
            'body_text' => 'Hi {{1}}, following up on your request.',
            'parameter_placeholders' => ['first_name'],
            'meta_status' => 'APPROVED',
        ]);

        $waTplModel::create([
            'whatsapp_account_id' => $account->id,
            'name' => 'pending_unusable_tpl',
            'language' => 'en_US',
            'category' => 'marketing',
            'header_type' => 'none',
            'body_text' => 'Draft offer',
            'parameter_placeholders' => [],
            'meta_status' => 'PENDING',
        ]);

        $selectorClass = $this->livewireClassIfExists(
            'App\\Modules\\Communications\\WhatsApp\\Livewire\\TemplateSelector'
        );
        if ($selectorClass === null) {
            $this->fail('TemplateSelector Livewire component must exist.');
        }

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        $component = Livewire::actingAs($admin)->test($selectorClass, ['conversationId' => $conversation->id]);
        $component->assertStatus(200);
        $component->assertSee('approved_reengage_tpl');
        $component->assertDontSee('pending_unusable_tpl');
    }
}
