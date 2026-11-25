<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F06CustomFieldsTest extends E2ETestCase
{
    public function test_t1_f06_01_org_admin_creates_definitions_across_all_seven_data_types(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF All Types Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $definitions = [
            ['entity_type' => 'lead', 'field_key' => 'cf_text', 'label' => 'CF Text', 'data_type' => 'text'],
            ['entity_type' => 'lead', 'field_key' => 'cf_number', 'label' => 'CF Number', 'data_type' => 'number'],
            ['entity_type' => 'contact', 'field_key' => 'cf_date', 'label' => 'CF Date', 'data_type' => 'date'],
            ['entity_type' => 'contact', 'field_key' => 'cf_boolean', 'label' => 'CF Boolean', 'data_type' => 'boolean'],
            ['entity_type' => 'account', 'field_key' => 'cf_select', 'label' => 'CF Select', 'data_type' => 'select', 'options' => ['Fintech', 'Healthcare']],
            ['entity_type' => 'deal', 'field_key' => 'cf_multiselect', 'label' => 'CF Multi', 'data_type' => 'multiselect', 'options' => ['Enterprise', 'Mid']],
            ['entity_type' => 'deal', 'field_key' => 'cf_textarea', 'label' => 'CF Notes', 'data_type' => 'textarea'],
        ];

        foreach ($definitions as $payload) {
            $res = $this->postJson('/ajax/custom-fields', $payload);
            $this->assertStatusIn($res, [200, 201]);
        }

        $this->assertSame(
            7,
            DB::table('custom_field_definitions')->where('organization_id', $org->id)->count()
        );
    }

    public function test_t1_f06_02_valid_custom_field_values_persist_across_all_four_entities(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Persist Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
            'is_required' => true,
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'contact',
            'field_key' => 'loyalty_score',
            'label' => 'Loyalty Score',
            'data_type' => 'number',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'account',
            'field_key' => 'is_enterprise',
            'label' => 'Enterprise Flag',
            'data_type' => 'boolean',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'deployment_regions',
            'label' => 'Deployment Regions',
            'data_type' => 'multiselect',
            'options' => ['NA', 'EU', 'APAC'],
        ])->assertStatus(201);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'custom_fields' => ['industry' => 'Fintech'],
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadData = $this->extractJsonData($leadRes);
        $this->assertSame('Fintech', $leadData['custom_fields']['industry'] ?? null);

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload([
            'custom_fields' => ['loyalty_score' => 95],
        ]));
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactData = $this->extractJsonData($contactRes);
        $this->assertSame(95, $contactData['custom_fields']['loyalty_score'] ?? null);

        $accountRes = $this->postJson('/ajax/accounts', $this->validAccountPayload([
            'custom_fields' => ['is_enterprise' => true],
        ]));
        $this->assertStatusIn($accountRes, [200, 201]);
        $accountData = $this->extractJsonData($accountRes);
        $this->assertTrue((bool) ($accountData['custom_fields']['is_enterprise'] ?? false));

        $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stageId = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'Global CF Deal',
            'amount' => 50000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'contact_id' => $contactData['id'],
            'custom_fields' => ['deployment_regions' => ['NA', 'EU']],
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $dealData = $this->extractJsonData($dealRes);
        $this->assertSame(['NA', 'EU'], $dealData['custom_fields']['deployment_regions'] ?? null);
    }

    public function test_t1_f06_03_lists_and_filters_custom_fields_by_entity_type_in_order(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Filter Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'second_field',
            'label' => 'Second Field',
            'data_type' => 'text',
            'order_index' => 2,
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'first_field',
            'label' => 'First Field',
            'data_type' => 'text',
            'order_index' => 1,
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'deal_only_field',
            'label' => 'Deal Only',
            'data_type' => 'number',
            'order_index' => 1,
        ])->assertStatus(201);

        $response = $this->getJson('/ajax/custom-fields?entity_type=lead');
        $response->assertStatus(200);

        $items = $this->extractJsonData($response);
        $this->assertCount(2, $items);
        $this->assertSame(['first_field', 'second_field'], array_column($items, 'field_key'));
    }

    public function test_t1_f06_04_optional_custom_field_accepts_omission_and_empty_string(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Optional Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'notes_extra',
            'label' => 'Extra Notes',
            'data_type' => 'text',
            'is_required' => false,
        ])->assertStatus(201);

        $omittedRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload());
        $this->assertStatusIn($omittedRes, [200, 201]);

        $emptyRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'custom_fields' => ['notes_extra' => ''],
        ]));
        $this->assertStatusIn($emptyRes, [200, 201]);
    }

    public function test_t1_f06_05_org_admin_deletes_custom_field_definition(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Delete Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $createRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'temp_field',
            'label' => 'Temporary Field',
            'data_type' => 'text',
        ]);
        $this->assertStatusIn($createRes, [200, 201]);
        $definitionId = $this->extractJsonData($createRes)['id'];

        $deleteRes = $this->deleteJson("/ajax/custom-fields/{$definitionId}");
        $this->assertStatusIn($deleteRes, [200, 204]);

        $this->assertNull(
            DB::table('custom_field_definitions')->where('id', $definitionId)->first()
        );
    }
}
