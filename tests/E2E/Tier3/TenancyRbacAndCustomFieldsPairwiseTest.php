<?php

namespace Tests\E2E\Tier3;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;

class TenancyRbacAndCustomFieldsPairwiseTest extends E2ETestCase
{
    public function test_t3_p01_organization_provisioning_seeds_default_pipeline_ready_for_deal_creation(): void
    {
        $primary = $this->registerOrganizationAndAdmin([
            'organization_name' => 'P01 Alpha Holdings',
            'organization_slug' => 'p01-alpha-holdings',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
        ]);
        $user = $primary['user'];
        $orgA = $primary['organization'];

        $secondaryRes = $this->actingAs($user)->post('/organizations', [
            'name' => 'P01 Beta Ventures',
            'slug' => 'p01-beta-ventures',
            'timezone' => 'Europe/London',
            'currency' => 'EUR',
        ]);
        $this->assertStatusIn($secondaryRes, [200, 201, 302]);

        $orgB = $user->organizations()->where('slug', 'p01-beta-ventures')->firstOrFail();

        $this->assertTrue(Schema::hasTable('pipelines'), 'pipelines table must exist.');
        $this->assertTrue(Schema::hasTable('pipeline_stages'), 'pipeline_stages table must exist.');

        foreach ([$orgA->id, $orgB->id] as $orgId) {
            $pipelineId = DB::table('pipelines')->where('organization_id', $orgId)->value('id');
            $this->assertNotNull($pipelineId, "Default pipeline must be seeded for org {$orgId}.");

            $stages = DB::table('pipeline_stages')
                ->where('pipeline_id', $pipelineId)
                ->orderBy('order_index')
                ->get();

            $this->assertGreaterThanOrEqual(2, $stages->where('category', 'open')->count());
            $this->assertGreaterThanOrEqual(1, $stages->where('category', 'won')->where('win_probability', 100)->count());
            $this->assertGreaterThanOrEqual(1, $stages->where('category', 'lost')->where('win_probability', 0)->count());
        }

        $orgBPipelineId = (int) DB::table('pipelines')->where('organization_id', $orgB->id)->value('id');
        $orgBFirstStageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $orgBPipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->value('id');

        $this->actingAsInOrganization($user, $orgB->id);

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload(['email' => 'p01@beta.test']));
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactId = (int) $this->extractJsonData($contactRes)['id'];

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'Seeded Pipeline Deal',
            'amount' => 4500,
            'currency' => 'EUR',
            'pipeline_id' => $orgBPipelineId,
            'pipeline_stage_id' => $orgBFirstStageId,
            'contact_id' => $contactId,
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $deal = $this->extractJsonData($dealRes);

        $this->assertSame((int) $orgB->id, (int) ($deal['organization_id'] ?? 0));
        $this->assertSame($orgBPipelineId, (int) ($deal['pipeline_id'] ?? 0));
        $this->assertSame($orgBFirstStageId, (int) ($deal['pipeline_stage_id'] ?? 0));
        $this->assertNotNull(DB::table('deals')->where('id', $deal['id'])->value('stage_entered_at'));
    }

    public function test_t3_p02_multi_org_switching_via_ajax_and_livewire_updates_rbac_permissions(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'P02 Admin Org']);
        $user = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'P02 ReadOnly Org']);
        $orgB = $tenantB['organization'];

        $this->createMemberInOrganization($orgB->id, 'read_only', [
            'email' => $user->email,
            'name' => $user->name,
        ]);

        // In Org A as org_admin: admin + write routes succeed
        $this->actingAsInOrganization($user, $orgA->id)
            ->get('/settings/team')
            ->assertStatus(200);

        $cfRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'p02_admin_field',
            'label' => 'P02 Admin Field',
            'data_type' => 'text',
        ]);
        $this->assertStatusIn($cfRes, [200, 201]);

        // Switch to Org B via AJAX without X-Organization-ID header override
        $switchRes = $this->clearTenantHeader()->postJson('/ajax/organizations/switch', [
            'organization_id' => $orgB->id,
        ]);
        $switchRes->assertStatus(200);
        $this->assertSame((int) $orgB->id, (int) session('active_organization_id'));
        $this->clearTenantContextIfBound();

        // In Org B as read_only: admin & mutation routes must return 403, while GET views succeed
        $this->get('/settings/team')->assertStatus(403);
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'p02_blocked_field',
            'label' => 'Blocked',
            'data_type' => 'text',
        ])->assertStatus(403);
        $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(403);
        $this->get('/leads')->assertStatus(200);

        // Switch back to Org A via Livewire OrganizationSwitcher if installed
        $switcherClass = $this->livewireClassIfExists(
            'App\\Modules\\Tenancy\\Livewire\\OrganizationSwitcher'
        );
        $this->assertNotNull($switcherClass, 'OrganizationSwitcher Livewire component must exist.');

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($orgB);
        }

        \Livewire\Livewire::actingAs($user)
            ->test($switcherClass)
            ->call('switchOrganization', (int) $orgA->id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame((int) $orgA->id, (int) session('active_organization_id'));
        $this->clearTenantContextIfBound();

        $this->actingAsInOrganization($user, $orgA->id)
            ->get('/settings/team')
            ->assertStatus(200);
    }

    public function test_t3_p03_row_level_isolation_scopes_required_custom_fields_per_organization(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'P03 Strict CF Org']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'P03 Free Org']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'segment',
                'label' => 'Segment',
                'data_type' => 'select',
                'options' => ['Enterprise', 'SMB'],
                'is_required' => true,
            ])
            ->assertStatus(201);

        // Org A rejects lead missing required custom field 'segment'
        $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'email' => 'missing-cf@orga.test',
        ]))->assertStatus(422);

        $validOrgALead = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'email' => 'valid-cf@orga.test',
            'custom_fields' => ['segment' => 'Enterprise'],
        ]));
        $this->assertStatusIn($validOrgALead, [200, 201]);

        // Org B must not see Org A's custom field definition and must accept leads without 'segment'
        $this->actingAsInOrganization($adminB, $orgB->id);

        $orgBCustomFields = $this->getJson('/ajax/custom-fields?entity_type=lead')->assertStatus(200);
        $fieldKeys = array_column($this->extractJsonData($orgBCustomFields), 'field_key');
        $this->assertNotContains('segment', $fieldKeys);

        $orgBLead = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'email' => 'no-segment-needed@orgb.test',
        ]));
        $this->assertStatusIn($orgBLead, [200, 201]);
    }

    public function test_t3_p04_api_bearer_token_rejects_mismatched_x_organization_id_and_scopes_queries(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'P04 Token Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'P04 Token Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'secret-b@orgb.test']))
            ->assertStatus(201);

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'visible-a@orga.test']))
            ->assertStatus(201);

        $tokenRes = $this->postJson('/ajax/team/tokens', ['name' => 'P04 Integration Key']);
        $this->assertStatusIn($tokenRes, [200, 201]);
        $tokenPayload = $this->extractJsonData($tokenRes);
        $plainToken = (string) ($tokenPayload['plain_text_token']
            ?? (is_string($tokenPayload['token'] ?? null) ? $tokenPayload['token'] : ''));
        $tokenId = (int) ($tokenPayload['token']['id'] ?? $tokenPayload['id'] ?? DB::table('api_tokens')->where('organization_id', $orgA->id)->value('id'));

        $this->assertNotSame('', $plainToken);
        $storedHash = DB::table('api_tokens')->where('id', $tokenId)->value('token_hash');
        $this->assertSame(hash('sha256', $plainToken), $storedHash);

        // Clear web session before using API Bearer token
        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        // Cross-org X-Organization-ID header must be rejected with 403
        $this->getJson('/api/me', [
            'Authorization' => "Bearer {$plainToken}",
            'X-Organization-ID' => (string) $orgB->id,
        ])->assertStatus(403);

        // Valid Org A token succeeds with Org A metadata
        $meRes = $this->getJson('/api/me', [
            'Authorization' => "Bearer {$plainToken}",
            'X-Organization-ID' => (string) $orgA->id,
        ])->assertStatus(200);
        $meRes->assertJsonPath('organization.id', $orgA->id);

        // Revoke token and verify subsequent Bearer call returns 401
        $this->actingAsInOrganization($adminA, $orgA->id)
            ->deleteJson("/ajax/team/tokens/{$tokenId}")
            ->assertStatus(200);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->getJson('/api/me', [
            'Authorization' => "Bearer {$plainToken}",
        ])->assertStatus(401);
    }

    public function test_t3_p05_hybrid_custom_fields_validate_and_persist_across_atomic_b2b_conversion(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P05 Atomic CF Convert']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry_tier',
            'label' => 'Industry Tier',
            'data_type' => 'select',
            'options' => ['Tier1', 'Tier2'],
            'is_required' => true,
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'account',
            'field_key' => 'vat_number',
            'label' => 'VAT Number',
            'data_type' => 'text',
            'validation_rules' => ['alpha_num'],
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'contact',
            'field_key' => 'vip_score',
            'label' => 'VIP Score',
            'data_type' => 'number',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'region',
            'label' => 'Region',
            'data_type' => 'select',
            'options' => ['NA', 'EMEA'],
            'is_required' => true,
        ])->assertStatus(201);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'Atomic Cloud Inc',
            'custom_fields' => ['industry_tier' => 'Tier1'],
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        // Missing required deal custom field 'region' must roll back the entire conversion transaction
        $failedConvert = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Rollback Deal',
            'deal_amount' => 15000,
            'account_custom_fields' => ['vat_number' => 'VAT9988'],
            'contact_custom_fields' => ['vip_score' => 88],
            'deal_custom_fields' => [],
        ]);
        $failedConvert->assertStatus(422);

        $this->assertSame(0, DB::table('accounts')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('contacts')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
        $this->assertNotSame('converted', DB::table('leads')->where('id', $leadId)->value('status'));

        // Valid conversion persists Account, Contact, and Deal atomically with their custom fields
        $okConvert = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Committed Cloud Deal',
            'deal_amount' => 15000,
            'account_custom_fields' => ['vat_number' => 'VAT9988'],
            'contact_custom_fields' => ['vip_score' => 88],
            'deal_custom_fields' => ['region' => 'EMEA'],
        ]);
        $this->assertStatusIn($okConvert, [200, 201]);

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $this->assertSame('converted', $leadRow->status);
        $this->assertNotNull($leadRow->converted_account_id);
        $this->assertNotNull($leadRow->converted_contact_id);
        $this->assertNotNull($leadRow->converted_deal_id);

        $accountCf = json_decode((string) DB::table('accounts')->where('id', $leadRow->converted_account_id)->value('custom_fields'), true);
        $contactCf = json_decode((string) DB::table('contacts')->where('id', $leadRow->converted_contact_id)->value('custom_fields'), true);
        $dealCf = json_decode((string) DB::table('deals')->where('id', $leadRow->converted_deal_id)->value('custom_fields'), true);

        $this->assertSame('VAT9988', $accountCf['vat_number'] ?? null);
        $this->assertSame(88, $contactCf['vip_score'] ?? null);
        $this->assertSame('EMEA', $dealCf['region'] ?? null);
    }
}
