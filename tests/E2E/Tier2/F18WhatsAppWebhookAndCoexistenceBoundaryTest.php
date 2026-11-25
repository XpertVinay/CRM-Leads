<?php

namespace Tests\E2E\Tier2;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F18WhatsAppWebhookAndCoexistenceBoundaryTest extends E2ETestCase
{
    public function test_t2_f18_01_invalid_hmac_and_bad_verify_token_return_403_with_zero_jobs(): void
    {
        config([
            'services.meta.webhook_verify_token' => 'expected_verify_token',
            'services.meta.app_secret' => 'expected_app_secret',
        ]);
        $this->enableDatabaseDrivers();

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=12345')
            ->assertStatus(403);

        $this->get('/api/webhooks/whatsapp?hub.mode=unsubscribe&hub.verify_token=expected_verify_token&hub.challenge=12345')
            ->assertStatus(403);

        $rawJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_any', '+15559001111', 'Tampered payload', 'wamid.TAMPER1')
        );

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . str_repeat('0', 64),
        ], $rawJson)->assertStatus(403);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $rawJson)->assertStatus(403);

        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_t2_f18_02_unknown_phone_number_id_is_noop_without_cross_tenant_leak(): void
    {
        config(['services.meta.app_secret' => 'secret_unknown_pn']);
        $this->enableDatabaseDrivers();

        $orgA = $this->registerOrganizationAndAdmin(['organization_name' => 'Known WA Org A'])['organization'];
        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';

        $waModel::create([
            'organization_id' => $orgA->id,
            'waba_id' => 'waba_known_a',
            'phone_number_id' => 'pn_known_a',
            'display_phone_number' => '+15551801001',
            'access_token_encrypted' => 'enc-known-a',
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);
        $this->clearTenantContextIfBound();

        $rawJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_does_not_exist', '+15559998888', 'Lost message', 'wamid.UNK1')
        );

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($rawJson, 'secret_unknown_pn'),
        ], $rawJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $this->assertSame(0, DB::table('contacts')->count());
        $this->assertSame(0, DB::table('whatsapp_conversations')->count());
        $this->assertSame(0, DB::table('whatsapp_messages')->count());

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->assertFalse($this->app->make($tenantContextClass)->hasTenant());
        }
    }

    public function test_t2_f18_03_duplicate_wamid_webhook_and_echo_are_idempotent_single_row(): void
    {
        config([
            'services.meta.app_secret' => 'secret_dup_wamid',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.graph_base_url' => 'https://graph.facebook.com',
        ]);
        $this->enableDatabaseDrivers();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.OUTBOUND_DEDUP_1']],
            ], 200),
        ]);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'WAMID Dedup Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_dedup',
                'phone_number_id' => 'pn_dedup',
                'display_phone_number' => '+15551802002',
                'access_token' => 'EAAG_dedup',
                'is_coexistence_enabled' => true,
            ])->assertStatusIn([200, 201]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $inboundJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_dedup', '+15559002002', 'Hello twice', 'wamid.INB_DEDUP_1')
        );
        $inboundSig = WhatsAppWebhookHelper::signPayload($inboundJson, 'secret_dup_wamid');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $inboundSig,
        ], $inboundJson)->assertStatus(200);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $inboundSig,
        ], $inboundJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $this->assertSame(
            1,
            DB::table('whatsapp_messages')->where('meta_message_id', 'wamid.INB_DEDUP_1')->count()
        );

        $convId = (int) DB::table('whatsapp_conversations')->where('organization_id', $org->id)->value('id');
        $this->actingAsInOrganization($admin, $org->id)
            ->postJson("/ajax/whatsapp/conversations/{$convId}/send-text", [
                'body' => 'Outbound once',
            ])
            ->assertStatusIn([200, 201]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $echoJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::coexistenceEchoPayload('pn_dedup', '+15559002002', 'Outbound once', 'wamid.OUTBOUND_DEDUP_1')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($echoJson, 'secret_dup_wamid'),
        ], $echoJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $this->assertSame(
            1,
            DB::table('whatsapp_messages')->where('meta_message_id', 'wamid.OUTBOUND_DEDUP_1')->count()
        );
    }

    public function test_t2_f18_04_coexistence_echo_does_not_falsely_open_24h_inbound_window(): void
    {
        config(['services.meta.app_secret' => 'secret_echo_window']);
        $this->enableDatabaseDrivers();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Echo Window Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $account = $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_ew',
            'phone_number_id' => 'pn_ew',
            'display_phone_number' => '+15551803003',
            'access_token_encrypted' => 'enc-ew',
            'is_coexistence_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559003003']))->assertStatus(201)
        )['id'];

        $expiredAt = now()->subHours(30);
        $convModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $conv = $convModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contactId,
            'customer_phone' => '+15559003003',
            'last_inbound_at' => $expiredAt,
            'last_message_at' => $expiredAt,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $echoJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::coexistenceEchoPayload('pn_ew', '+15559003003', 'Mobile outbound echo', 'wamid.ECHO_WIN_1')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($echoJson, 'secret_echo_window'),
        ], $echoJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $conv->refresh();
        $this->assertFalse($conv->isWindowActive(), 'Outbound coexistence echo must NOT re-open the 24-hour inbound window.');
        $this->assertSame($expiredAt->timestamp, $conv->last_inbound_at->timestamp);
    }

    public function test_t2_f18_05_webhook_route_configures_3000_per_minute_throttle(): void
    {
        $webhookRoute = collect(Route::getRoutes()->getRoutes())
            ->first(fn (RoutingRoute $r) => in_array('POST', $r->methods(), true) && $r->uri() === 'api/webhooks/whatsapp');

        $this->assertNotNull($webhookRoute, 'POST /api/webhooks/whatsapp route must be registered.');

        $middleware = $webhookRoute->gatherMiddleware();
        $throttleMiddleware = collect($middleware)->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
        $this->assertNotNull($throttleMiddleware, 'POST /api/webhooks/whatsapp must declare throttle middleware.');

        if (preg_match('/^throttle:(\d+),(\d+)/', $throttleMiddleware, $matches)) {
            $this->assertSame(3000, (int) $matches[1]);
            $this->assertSame(1, (int) $matches[2]);
        } else {
            $limiterName = substr($throttleMiddleware, strlen('throttle:'));
            $limiter = RateLimiter::limiter($limiterName);
            $this->assertNotNull($limiter, "RateLimiter '{$limiterName}' must be registered.");
            $limit = $limiter(request());
            $maxAttempts = is_array($limit) ? ($limit[0]->maxAttempts ?? null) : ($limit->maxAttempts ?? null);
            $this->assertSame(3000, (int) $maxAttempts);
        }
    }
}
