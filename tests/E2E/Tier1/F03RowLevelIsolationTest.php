<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F03RowLevelIsolationTest extends E2ETestCase
{
    public function test_t1_f03_01_list_endpoints_return_only_active_organization_records(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Isolation Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Isolation Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        foreach ([$tenantA, $tenantB] as $tenant) {
            $admin = $tenant['user'];
            $org = $tenant['organization'];

            $this->actingAsInOrganization($admin, $org->id);

            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201);
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload())->assertStatus(201);

            $this->postJson('/ajax/accounts', $this->validAccountPayload())->assertStatus(201);
            $this->postJson('/ajax/accounts', $this->validAccountPayload())->assertStatus(201);

            $contact1 = $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201);
            $contact2 = $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201);
            $contactId = $this->extractJsonData($contact1)['id'] ?? null;

            $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
            $stageId = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');

            $this->postJson('/ajax/deals', [
                'title' => "Deal 1 {$org->slug}",
                'amount' => 1000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $stageId,
                'contact_id' => $contactId,
            ])->assertStatus(201);

            $this->postJson('/ajax/deals', [
                'title' => "Deal 2 {$org->slug}",
                'amount' => 2000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $stageId,
                'contact_id' => $this->extractJsonData($contact2)['id'] ?? $contactId,
            ])->assertStatus(201);
        }

        $this->actingAsInOrganization($adminA, $orgA->id);

        foreach (['/ajax/leads', '/ajax/accounts', '/ajax/contacts', '/ajax/deals'] as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200);

            $items = $this->extractJsonData($response);
            $this->assertCount(2, $items, "Expected 2 records for Org A on {$endpoint}.");

            foreach ($items as $item) {
                $this->assertSame((int) $orgA->id, (int) ($item['organization_id'] ?? 0));
                $this->assertNotSame((int) $orgB->id, (int) ($item['organization_id'] ?? 0));
            }
        }
    }

    public function test_t1_f03_02_creating_entities_auto_populates_active_organization_id(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'AutoOrg A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload());
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadData = $this->extractJsonData($leadRes);
        $this->assertSame((int) $orgA->id, (int) ($leadData['organization_id'] ?? DB::table('leads')->where('id', $leadData['id'])->value('organization_id')));

        $accountRes = $this->postJson('/ajax/accounts', $this->validAccountPayload());
        $this->assertStatusIn($accountRes, [200, 201]);
        $accountData = $this->extractJsonData($accountRes);
        $this->assertSame((int) $orgA->id, (int) ($accountData['organization_id'] ?? DB::table('accounts')->where('id', $accountData['id'])->value('organization_id')));

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload());
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactData = $this->extractJsonData($contactRes);
        $this->assertSame((int) $orgA->id, (int) ($contactData['organization_id'] ?? DB::table('contacts')->where('id', $contactData['id'])->value('organization_id')));
    }

    public function test_t1_f03_03_search_queries_remain_strictly_tenant_scoped(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Search Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Search Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/contacts', $this->validContactPayload([
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice.smith@orga.test',
            ]))
            ->assertStatus(201);

        $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/contacts', $this->validContactPayload([
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob.smith@orgb.test',
            ]))
            ->assertStatus(201);

        $searchRes = $this->actingAsInOrganization($adminA, $orgA->id)
            ->getJson('/ajax/contacts?search=Smith');

        $searchRes->assertStatus(200);
        $searchRes->assertSee('alice.smith@orga.test');
        $searchRes->assertDontSee('bob.smith@orgb.test');
    }

    public function test_t1_f03_04_tracking_links_and_custom_fields_are_scoped_per_organization(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Links Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Links Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Org A Promo Link',
            'code' => 'org-a-promo',
            'destination_url' => 'https://example.test/a',
            'channel' => 'ad',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'org_a_tier',
            'label' => 'Org A Tier',
            'data_type' => 'text',
        ])->assertStatus(201);

        $this->actingAsInOrganization($adminB, $orgB->id);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Org B Secret Link',
            'code' => 'org-b-secret',
            'destination_url' => 'https://example.test/b',
            'channel' => 'email',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'org_b_tier',
            'label' => 'Org B Tier',
            'data_type' => 'text',
        ])->assertStatus(201);

        $this->actingAsInOrganization($adminA, $orgA->id);

        $linksRes = $this->get('/tracking-links');
        $linksRes->assertStatus(200);
        $linksRes->assertSee('org-a-promo');
        $linksRes->assertDontSee('org-b-secret');

        $fieldsRes = $this->getJson('/ajax/custom-fields');
        $fieldsRes->assertStatus(200);
        $fieldKeys = array_column($this->extractJsonData($fieldsRes), 'field_key');
        $this->assertContains('org_a_tier', $fieldKeys);
        $this->assertNotContains('org_b_tier', $fieldKeys);
    }

    public function test_t1_f03_05_direct_show_routes_succeed_for_own_organization_records(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Show Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'Visible Show Company',
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadId = $this->extractJsonData($leadRes)['id'];

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload());
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactId = $this->extractJsonData($contactRes)['id'];

        $pipelineId = DB::table('pipelines')->where('organization_id', $orgA->id)->value('id');
        $stageId = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'Visible Show Deal',
            'amount' => 15000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'contact_id' => $contactId,
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $dealId = $this->extractJsonData($dealRes)['id'];

        $leadShow = $this->get("/leads/{$leadId}");
        $leadShow->assertStatus(200);
        $leadShow->assertSee('Visible Show Company');

        $dealShow = $this->get("/deals/{$dealId}");
        $dealShow->assertStatus(200);
        $dealShow->assertSee('Visible Show Deal');
    }
}
