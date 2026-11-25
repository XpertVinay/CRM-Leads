<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F06CustomFieldsBoundaryTest extends E2ETestCase
{
    public function test_t2_f06_01_select_and_multiselect_reject_values_outside_options(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Select Boundary Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'segments',
            'label' => 'Segments',
            'data_type' => 'multiselect',
            'options' => ['SMB', 'Enterprise'],
        ])->assertStatus(201);

        $badSelectRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'custom_fields' => ['industry' => 'Automotive'],
        ]));
        $badSelectRes->assertStatus(422);

        $badMultiRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'custom_fields' => ['segments' => ['SMB', 'InvalidOption']],
        ]));
        $badMultiRes->assertStatus(422);

        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f06_02_missing_or_empty_required_custom_field_is_rejected(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Required Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'contract_term',
            'label' => 'Contract Term',
            'data_type' => 'number',
            'is_required' => true,
        ])->assertStatus(201);

        $contactId = $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stageId = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');

        $omittedRes = $this->postJson('/ajax/deals', [
            'title' => 'Missing Required CF Deal',
            'amount' => 7000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'contact_id' => $contactId,
        ]);
        $omittedRes->assertStatus(422);

        $emptyRes = $this->postJson('/ajax/deals', [
            'title' => 'Empty Required CF Deal',
            'amount' => 7000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'contact_id' => $contactId,
            'custom_fields' => ['contract_term' => ''],
        ]);
        $emptyRes->assertStatus(422);

        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f06_03_type_mismatch_on_number_date_or_boolean_is_rejected(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Type Mismatch Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'contact',
            'field_key' => 'score',
            'label' => 'Score',
            'data_type' => 'number',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'contact',
            'field_key' => 'dob',
            'label' => 'Date of Birth',
            'data_type' => 'date',
        ])->assertStatus(201);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'contact',
            'field_key' => 'vip',
            'label' => 'VIP',
            'data_type' => 'boolean',
        ])->assertStatus(201);

        $this->postJson('/ajax/contacts', $this->validContactPayload([
            'custom_fields' => ['score' => 'abc'],
        ]))->assertStatus(422);

        $this->postJson('/ajax/contacts', $this->validContactPayload([
            'custom_fields' => ['dob' => 'not-a-date'],
        ]))->assertStatus(422);

        $this->postJson('/ajax/contacts', $this->validContactPayload([
            'custom_fields' => ['vip' => 'definitely-maybe'],
        ]))->assertStatus(422);

        $this->assertSame(0, DB::table('contacts')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f06_04_duplicate_field_key_per_org_entity_rejected_while_other_org_succeeds(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Key Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Key Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $payload = [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'text',
        ];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/custom-fields', $payload)
            ->assertStatus(201);

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/custom-fields', $payload)
            ->assertStatus(422);

        $orgBRes = $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/custom-fields', $payload);
        $this->assertStatusIn($orgBRes, [200, 201]);
    }

    public function test_t2_f06_05_custom_field_definitions_remain_isolated_across_organizations(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Iso Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'CF Iso Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'industry',
                'label' => 'Industry',
                'data_type' => 'text',
                'is_required' => true,
            ])
            ->assertStatus(201);

        $orgBLeadRes = $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload());
        $this->assertStatusIn($orgBLeadRes, [200, 201]);

        $orgALeadRes = $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload());
        $orgALeadRes->assertStatus(422);
    }
}
