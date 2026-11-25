<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Communications\WhatsApp\Livewire\TemplateSelector;
use App\Modules\Communications\WhatsApp\Livewire\WhatsAppChatWidget;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_connect_waba_encrypts_token_and_syncs_templates(): void
    {
        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*/message_templates*' => Http::response([
                'data' => [
                    [
                        'name' => 'welcome_follow_up',
                        'language' => 'en_US',
                        'category' => 'UTILITY',
                        'status' => 'APPROVED',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hello {{1}}, thanks for reaching out!'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $plainToken = 'EAAG_secret_system_token';

        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.whatsapp.connect'), [
                'waba_id' => 'waba_001',
                'phone_number_id' => 'pn_001',
                'display_phone_number' => '+15550001111',
                'access_token' => $plainToken,
                'is_coexistence_enabled' => true,
            ])
            ->assertCreated();

        $account = WhatsAppAccount::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $this->assertNotSame($plainToken, $account->access_token_encrypted);
        $this->assertSame($plainToken, Crypt::decryptString($account->access_token_encrypted));
        $this->assertTrue($account->is_coexistence_enabled);

        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.whatsapp.templates.sync'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('whatsapp_templates', [
            'whatsapp_account_id' => $account->id,
            'name' => 'welcome_follow_up',
            'meta_status' => 'APPROVED',
        ]);
    }

    public function test_webhook_verification_hmac_signature_inbound_and_coexistence_echo(): void
    {
        config([
            'services.meta.webhook_verify_token' => 'verify_secret_123',
            'services.meta.app_secret' => 'app_secret_123',
            'queue.default' => 'database',
        ]);

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $account = WhatsAppAccount::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_wh',
            'phone_number_id' => 'pn_wh',
            'display_phone_number' => '+15550002222',
            'access_token_encrypted' => Crypt::encryptString('tok'),
            'is_coexistence_enabled' => true,
            'is_active' => true,
        ]);

        // GET verify
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify_secret_123&hub.challenge=CHAL99')
            ->assertOk()
            ->assertSee('CHAL99');

        // Invalid signature rejected
        $this->postJson('/api/webhooks/whatsapp', ['entry' => []], [
            'X-Hub-Signature-256' => 'sha256=bad',
        ])->assertForbidden();

        // Valid inbound message
        $inboundPayload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba_wh',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'pn_wh'],
                        'contacts' => [['wa_id' => '15559990001', 'profile' => ['name' => 'Sam Buyer']]],
                        'messages' => [[
                            'from' => '15559990001',
                            'id' => 'wamid.IN1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Hi there'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $inboundPayload, 'app_secret_123'),
        ], $inboundPayload)->assertOk();

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'organization_id' => $org->id,
            'meta_message_id' => 'wamid.IN1',
            'direction' => 'inbound',
            'body' => 'Hi there',
        ]);

        // Coexistence echo
        $echoPayload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba_wh',
                'changes' => [[
                    'field' => 'smb_message_echoes',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'pn_wh'],
                        'message_echoes' => [[
                            'from' => '15550002222',
                            'to' => '15559990001',
                            'id' => 'wamid.ECHO1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Sent from mobile app'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $echoPayload, 'app_secret_123'),
        ], $echoPayload)->assertOk();

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'organization_id' => $org->id,
            'meta_message_id' => 'wamid.ECHO1',
            'direction' => 'outbound',
            'is_coexistence_echo' => true,
        ]);
    }

    public function test_24h_window_enforcement_templates_and_opt_out_consent(): void
    {
        config([
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.OUT1']],
            ], 200),
        ]);

        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $account = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_win',
            'phone_number_id' => 'pn_win',
            'display_phone_number' => '+15550003333',
            'access_token_encrypted' => Crypt::encryptString('tok'),
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Elena',
            'phone' => '+15559990002',
        ]);

        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.whatsapp.consents.store'), [
                'contact_id' => $contact->id,
                'phone' => '+15559990002',
                'is_opted_in' => true,
                'consent_source' => 'web_form',
            ])
            ->assertCreated();

        $expiredConv = WhatsAppConversation::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'customer_phone' => '+15559990002',
            'last_inbound_at' => now()->subHours(26),
            'last_message_at' => now()->subHours(26),
        ]);

        $this->assertFalse($expiredConv->isWindowActive());

        // Free-form text blocked outside 24h window
        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.whatsapp.send-text', $expiredConv), ['body' => 'Blocked text'])
            ->assertUnprocessable();

        $template = WhatsAppTemplate::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'name' => 'follow_up',
            'language' => 'en_US',
            'category' => 'utility',
            'body_text' => 'Hello {{1}}',
            'parameter_placeholders' => ['name'],
            'meta_status' => 'APPROVED',
        ]);

        // Template succeeds outside 24h window
        $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.whatsapp.send-template', $expiredConv), [
                'template_id' => $template->id,
                'parameters' => ['Elena'],
            ])
            ->assertCreated();

        // Livewire components render cleanly
        $this->actingAsOrgMember($admin, $org);
        Livewire::test(WhatsAppChatWidget::class, ['conversationId' => $expiredConv->id])
            ->assertSee('24h Window Expired');
        Livewire::test(TemplateSelector::class)
            ->assertSee('follow_up');
    }
}
