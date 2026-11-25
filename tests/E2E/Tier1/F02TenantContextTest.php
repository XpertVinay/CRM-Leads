<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F02TenantContextTest extends E2ETestCase
{
    public function test_t1_f02_01_resolves_tenant_from_session_active_organization_id(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Session Org A']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Session Org B',
            'slug' => 'session-org-b',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
        $orgB = $user->organizations()->where('slug', 'session-org-b')->firstOrFail();

        $leadA = $this->validB2BLeadPayload(['email' => 'leada@orga.test', 'company_name' => 'AlphaCo']);
        $leadB = $this->validB2BLeadPayload(['email' => 'leadb@orgb.test', 'company_name' => 'BetaCo']);

        $this->actingAsInOrganization($user, $orgA->id)->postJson('/ajax/leads', $leadA)->assertStatus(201);
        $this->actingAsInOrganization($user, $orgB->id)->postJson('/ajax/leads', $leadB)->assertStatus(201);

        $response = $this->clearTenantHeader()
            ->actingAs($user)
            ->withSession(['active_organization_id' => $orgB->id])
            ->get('/leads');

        $response->assertStatus(200);
        $response->assertSee('leadb@orgb.test');
        $response->assertDontSee('leada@orga.test');
    }

    public function test_t1_f02_02_resolves_tenant_from_x_organization_id_header(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Header Org A']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Header Org B',
            'slug' => 'header-org-b',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
        $orgB = $user->organizations()->where('slug', 'header-org-b')->firstOrFail();

        $this->actingAsInOrganization($user, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'header-a@orga.test']))
            ->assertStatus(201);

        $this->actingAsInOrganization($user, $orgB->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'header-b@orgb.test']))
            ->assertStatus(201);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $orgA->id])
            ->getJson('/ajax/leads', $this->tenantHeaders($orgB->id));

        $response->assertStatus(200);
        $items = $this->extractJsonData($response);
        $this->assertNotEmpty($items);

        $emails = array_column($items, 'email');
        $this->assertContains('header-b@orgb.test', $emails);
        $this->assertNotContains('header-a@orga.test', $emails);
    }

    public function test_t1_f02_03_falls_back_to_sole_active_organization_when_no_session_or_header(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Sole Org']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAsInOrganization($user, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'sole-fallback@orga.test']))
            ->assertStatus(201);

        $response = $this->clearTenantHeader()
            ->actingAs($user)
            ->flushSession()
            ->get('/leads');

        $response->assertStatus(200);
        $response->assertSee('sole-fallback@orga.test');
    }

    public function test_t1_f02_04_maintains_deterministic_context_isolation_across_sequential_requests(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Seq Org A']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Seq Org B',
            'slug' => 'seq-org-b',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
        $orgB = $user->organizations()->where('slug', 'seq-org-b')->firstOrFail();

        $this->actingAsInOrganization($user, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'seq-a@orga.test']))
            ->assertStatus(201);

        $this->actingAsInOrganization($user, $orgB->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'seq-b@orgb.test']))
            ->assertStatus(201);

        $resA = $this->actingAs($user)->getJson('/ajax/leads', $this->tenantHeaders($orgA->id));
        $resA->assertStatus(200);
        $emailsA = array_column($this->extractJsonData($resA), 'email');
        $this->assertContains('seq-a@orga.test', $emailsA);
        $this->assertNotContains('seq-b@orgb.test', $emailsA);

        $resB = $this->actingAs($user)->getJson('/ajax/leads', $this->tenantHeaders($orgB->id));
        $resB->assertStatus(200);
        $emailsB = array_column($this->extractJsonData($resB), 'email');
        $this->assertContains('seq-b@orgb.test', $emailsB);
        $this->assertNotContains('seq-a@orga.test', $emailsB);
    }

    public function test_t1_f02_05_webhook_resolves_tenant_from_phone_number_id(): void
    {
        config(['services.meta.app_secret' => 'test_secret']);
        $this->enableDatabaseDrivers();

        $orgA = $this->registerOrganizationAndAdmin(['organization_name' => 'WA Org A'])['organization'];
        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'WA Org B'])['organization'];

        $this->assertTrue(Schema::hasTable('whatsapp_accounts'), 'whatsapp_accounts table must exist.');

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $orgA->id,
            'waba_id' => 'waba_a_001',
            'phone_number_id' => 'pn_A',
            'display_phone_number' => '+15550000011',
            'access_token_encrypted' => 'token-a',
            'is_active' => true,
        ]);

        $waModel::create([
            'organization_id' => $orgB->id,
            'waba_id' => 'waba_b_002',
            'phone_number_id' => 'pn_B',
            'display_phone_number' => '+15550000022',
            'access_token_encrypted' => 'token-b',
            'is_active' => true,
        ]);

        $payload = WhatsAppWebhookHelper::inboundTextPayload('pn_B', '15559990001', 'Hello Org B');
        $rawJson = WhatsAppWebhookHelper::encode($payload);
        $signature = WhatsAppWebhookHelper::signPayload($rawJson, 'test_secret');

        auth()->logout();
        $this->clearTenantHeader()->flushSession();

        $response = $this->call(
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

        $response->assertStatus(200);
        $this->drainDatabaseQueue();

        $this->assertSame(1, DB::table('whatsapp_conversations')->where('organization_id', $orgB->id)->count());
        $this->assertSame(1, DB::table('whatsapp_messages')->where('organization_id', $orgB->id)->count());
        $this->assertSame(0, DB::table('whatsapp_conversations')->where('organization_id', $orgA->id)->count());
        $this->assertSame(0, DB::table('whatsapp_messages')->where('organization_id', $orgA->id)->count());
    }
}
