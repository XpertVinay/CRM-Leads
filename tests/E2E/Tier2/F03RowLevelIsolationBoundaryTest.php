<?php

namespace Tests\E2E\Tier2;

use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F03RowLevelIsolationBoundaryTest extends E2ETestCase
{
    public function test_t2_f03_01_cross_tenant_show_update_delete_returns_403_or_404(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadB = $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201));
        $contactB = $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload(['first_name' => 'OriginalB']))->assertStatus(201));
        $accountB = $this->extractJsonData($this->postJson('/ajax/accounts', $this->validAccountPayload(['name' => 'OriginalAccountB']))->assertStatus(201));

        $this->actingAsInOrganization($adminA, $orgA->id);

        $showLead = $this->get("/leads/{$leadB['id']}");
        $this->assertStatusIn($showLead, [403, 404]);

        $updateContact = $this->putJson("/ajax/contacts/{$contactB['id']}", ['first_name' => 'Tampered']);
        $this->assertStatusIn($updateContact, [403, 404]);

        $deleteAccount = $this->deleteJson("/ajax/accounts/{$accountB['id']}");
        $this->assertStatusIn($deleteAccount, [403, 404]);

        $this->assertSame('OriginalB', DB::table('contacts')->where('id', $contactB['id'])->value('first_name'));
        $this->assertSame('OriginalAccountB', DB::table('accounts')->where('id', $accountB['id'])->value('name'));
    }

    public function test_t2_f03_02_payload_spoofing_organization_id_is_ignored_or_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Spoof Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'Spoof Org B'])['organization'];

        $response = $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'organization_id' => $orgB->id,
                'email' => 'spoof-attempt@acme.test',
            ]));

        $status = $response->getStatusCode();
        $this->assertStatusIn($response, [200, 201, 403, 422]);

        if (in_array($status, [200, 201], true)) {
            $created = $this->extractJsonData($response);
            $this->assertSame((int) $orgA->id, (int) ($created['organization_id'] ?? 0));
        }

        $this->assertSame(0, DB::table('leads')->where('organization_id', $orgB->id)->count());
    }

    public function test_t2_f03_03_cross_tenant_foreign_key_injection_on_deal_creation_is_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'FK Inject Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'FK Inject Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $contactBId = $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $pipelineBId = DB::table('pipelines')->where('organization_id', $orgB->id)->value('id');
        $stageBId = DB::table('pipeline_stages')->where('pipeline_id', $pipelineBId)->where('category', 'open')->value('id');

        $response = $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/deals', [
                'title' => 'Cross Tenant FK Hijack',
                'amount' => 5000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineBId,
                'pipeline_stage_id' => $stageBId,
                'contact_id' => $contactBId,
            ]);

        $this->assertStatusIn($response, [403, 404, 422]);
        $this->assertSame(0, DB::table('deals')->where('organization_id', $orgA->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $orgB->id)->count());
    }

    public function test_t2_f03_04_cross_tenant_livewire_action_targeting_other_org_record_is_blocked(): void
    {
        $this->assertTrue($this->hasLivewireInstalled(), 'Livewire 3 must be installed.');

        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Guard Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Guard Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $defRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'org_b_protected_field',
            'label' => 'Org B Protected',
            'data_type' => 'text',
        ]);
        $this->assertStatusIn($defRes, [200, 201]);
        $orgBDefId = (int) $this->extractJsonData($defRes)['id'];

        $pageRes = $this->actingAsInOrganization($adminA, $orgA->id)
            ->get('/settings/custom-fields');
        $pageRes->assertStatus(200);

        $snapshotJson = $this->extractLivewireSnapshot($pageRes, 'custom-fields.custom-field-manager');
        $this->assertNotNull($snapshotJson, 'Could not extract Livewire snapshot for CustomFieldManager.');

        $csrfToken = $this->extractCsrfToken($pageRes);

        $this->actingAsInOrganization($adminA, $orgA->id);
        $this->assertFalse(app(TenantContext::class)->hasTenant());

        $headers = ['X-Livewire' => 'true'];
        if ($csrfToken !== null) {
            $headers['X-CSRF-TOKEN'] = $csrfToken;
        }

        $updateRes = $this->postJson('/livewire/update', [
            'components' => [
                [
                    'snapshot' => $snapshotJson,
                    'updates' => [],
                    'calls' => [
                        [
                            'path' => '',
                            'method' => 'deleteField',
                            'params' => [$orgBDefId],
                        ],
                    ],
                ],
            ],
        ], $headers);

        $this->assertStatusIn($updateRes, [403, 404], 'Cross-tenant Livewire action must be blocked.');
        $this->assertSame(
            (int) $orgA->id,
            app(TenantContext::class)->getTenantId(),
            'EnsureTenantContext persistent middleware must resolve Org A on POST /livewire/update.'
        );
        $this->assertTrue(
            DB::table('custom_field_definitions')->where('id', $orgBDefId)->exists(),
            'Org B record must remain intact after cross-tenant Livewire attempt.'
        );

        $repA = $this->createMemberInOrganization($orgA->id, 'sales_rep')['user'];
        $this->actingAsInOrganization($repA, $orgA->id);
        $this->assertFalse(app(TenantContext::class)->hasTenant());

        $repRes = $this->postJson('/livewire/update', [
            'components' => [
                [
                    'snapshot' => $snapshotJson,
                    'updates' => [],
                    'calls' => [
                        [
                            'path' => '',
                            'method' => 'deleteField',
                            'params' => [$orgBDefId],
                        ],
                    ],
                ],
            ],
        ], $headers);

        $repRes->assertStatus(403);
        $this->assertTrue(
            DB::table('custom_field_definitions')->where('id', $orgBDefId)->exists(),
            'Org B record must remain intact after unauthorized role Livewire replay.'
        );
    }

    public function test_t2_f03_05_queued_webhook_job_isolates_tenant_context_without_prior_session(): void
    {
        config(['services.meta.app_secret' => 'queue_secret']);
        $this->enableDatabaseDrivers();

        $orgA = $this->registerOrganizationAndAdmin(['organization_name' => 'Queue WA Org A'])['organization'];
        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'Queue WA Org B'])['organization'];

        $this->assertTrue(Schema::hasTable('whatsapp_accounts'), 'whatsapp_accounts table must exist.');

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $this->assertTrue(class_exists($waModel), 'WhatsAppAccount model must exist.');

        $waModel::create([
            'organization_id' => $orgA->id,
            'waba_id' => 'waba_qa_1',
            'phone_number_id' => 'pn_qa_A',
            'display_phone_number' => '+15551110001',
            'access_token_encrypted' => 'enc-a',
            'is_active' => true,
        ]);

        $waModel::create([
            'organization_id' => $orgB->id,
            'waba_id' => 'waba_qb_2',
            'phone_number_id' => 'pn_qb_B',
            'display_phone_number' => '+15551110002',
            'access_token_encrypted' => 'enc-b',
            'is_active' => true,
        ]);

        Auth::logout();
        $this->clearTenantHeader()->flushSession();
        $this->clearTenantContextIfBound();

        $this->assertFalse(app(TenantContext::class)->hasTenant());

        $payload = WhatsAppWebhookHelper::inboundTextPayload('pn_qb_B', '15558880002', 'Queued message for Org B');
        $rawJson = WhatsAppWebhookHelper::encode($payload);
        $signature = WhatsAppWebhookHelper::signPayload($rawJson, 'queue_secret');

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

        $this->assertFalse(
            app(TenantContext::class)->hasTenant(),
            'TenantContext must not leak globally after queued webhook job finishes.'
        );
    }
}
