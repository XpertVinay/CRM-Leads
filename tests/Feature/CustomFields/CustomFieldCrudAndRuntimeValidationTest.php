<?php

namespace Tests\Feature\CustomFields;

use App\Modules\CustomFields\Livewire\CustomFieldManager;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class CustomFieldCrudAndRuntimeValidationTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_ajax_custom_field_crud_and_runtime_validation_for_all_entities(): void
    {
        ['organization' => $org1, 'user' => $admin1] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $org2, 'user' => $admin2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $this->actingAsOrgMember($admin1, $org1);

        // Create select field on lead
        $createRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
            'is_required' => true,
            'order_index' => 1,
        ])->assertStatus(201);

        $defId = $createRes->json('data.id');

        // Duplicate (organization_id, entity_type, field_key) returns 422
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry Duplicate',
            'data_type' => 'text',
        ])->assertStatus(422);

        // Same field_key on a different entity_type succeeds
        foreach (['contact', 'account', 'deal'] as $entity) {
            $this->postJson('/ajax/custom-fields', [
                'entity_type' => $entity,
                'field_key' => 'industry',
                'label' => 'Industry',
                'data_type' => 'select',
                'options' => ['Fintech', 'Healthcare'],
                'is_required' => true,
            ])->assertStatus(201);
        }

        // Same field_key in Org 2 also succeeds
        $this->actingAsOrgMember($admin2, $org2);
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Org 2 Industry',
            'data_type' => 'text',
        ])->assertStatus(201);

        // Runtime validation on Org 1 lead
        $validator = app(CustomFieldValidationService::class);
        $this->assertSame(
            ['industry' => 'Fintech'],
            $validator->validate('lead', ['industry' => 'Fintech'], $org1->id),
        );

        try {
            $validator->validate('lead', ['industry' => 'Automotive'], $org1->id);
            $this->fail('Expected ValidationException for invalid option.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('industry', $e->errors());
        }

        // Update and delete in Org 1
        $this->actingAsOrgMember($admin1, $org1);
        $this->patchJson("/ajax/custom-fields/{$defId}", [
            'label' => 'Target Industry',
            'options' => ['Fintech', 'Healthcare', 'SaaS'],
        ])->assertOk()->assertJsonPath('data.label', 'Target Industry');

        $this->getJson('/ajax/custom-fields?entity_type=lead')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/ajax/custom-fields/{$defId}")->assertOk();
        $this->assertDatabaseMissing('custom_field_definitions', ['id' => $defId]);
    }

    public function test_livewire_custom_field_manager_crud_and_authorization(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['user' => $rep] = $this->createOrgWithUser(TenantRole::SalesRep->value);
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);

        $this->actingAsOrgMember($admin, $org);

        Livewire::test(CustomFieldManager::class)
            ->call('setEntityType', 'deal')
            ->set('fieldKey', 'contract_tier')
            ->set('label', 'Contract Tier')
            ->set('dataType', 'select')
            ->set('optionsInput', 'Silver, Gold, Platinum')
            ->set('isRequired', true)
            ->call('createField')
            ->assertHasNoErrors();

        $def = CustomFieldDefinition::query()
            ->where('entity_type', 'deal')
            ->where('field_key', 'contract_tier')
            ->firstOrFail();

        $this->assertSame(['Silver', 'Gold', 'Platinum'], $def->options);

        // Sales rep is blocked by Policy inside Livewire action
        $this->actingAsOrgMember($rep, $org);
        Livewire::test(CustomFieldManager::class)
            ->call('deleteField', $def->id)
            ->assertForbidden();

        $this->assertDatabaseHas('custom_field_definitions', ['id' => $def->id]);
    }

    public function test_update_select_and_multiselect_rejects_null_or_empty_options_without_wiping_existing_options(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->actingAsOrgMember($admin, $org);

        $selectRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'tier',
            'label' => 'Tier',
            'data_type' => 'select',
            'options' => ['Gold', 'Gold', 'Silver'],
            'is_required' => true,
        ])->assertStatus(201);

        $selectDef = CustomFieldDefinition::findOrFail($selectRes->json('data.id'));
        $this->assertSame(['Gold', 'Silver'], $selectDef->options);

        $multiRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'regions',
            'label' => 'Regions',
            'data_type' => 'multiselect',
            'options' => ['NA', 'EMEA'],
            'is_required' => false,
        ])->assertStatus(201);

        $multiDef = CustomFieldDefinition::findOrFail($multiRes->json('data.id'));

        // Explicit null or empty options on select/multiselect must return 422 and preserve existing options
        $this->patchJson("/ajax/custom-fields/{$selectDef->id}", [
            'label' => 'Tier Updated',
            'options' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->assertSame(['Gold', 'Silver'], $selectDef->fresh()->options);

        $this->patchJson("/ajax/custom-fields/{$selectDef->id}", [
            'options' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->assertSame(['Gold', 'Silver'], $selectDef->fresh()->options);

        $this->patchJson("/ajax/custom-fields/{$multiDef->id}", [
            'options' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->patchJson("/ajax/custom-fields/{$multiDef->id}", [
            'options' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->assertSame(['NA', 'EMEA'], $multiDef->fresh()->options);

        // Omitting options on PATCH preserves existing options
        $this->patchJson("/ajax/custom-fields/{$selectDef->id}", [
            'label' => 'Tier Renamed',
        ])->assertOk()->assertJsonPath('data.label', 'Tier Renamed');

        $this->assertSame(['Gold', 'Silver'], $selectDef->fresh()->options);

        // Updating options deduplicates trimmed values
        $this->patchJson("/ajax/custom-fields/{$selectDef->id}", [
            'options' => ['Platinum', 'Platinum', 'Gold'],
        ])->assertOk();

        $this->assertSame(['Platinum', 'Gold'], $selectDef->fresh()->options);

        // Livewire CustomFieldManager rejects blank comma-separated options for select/multiselect
        Livewire::test(CustomFieldManager::class)
            ->set('entityType', 'lead')
            ->set('fieldKey', 'lw_empty_select')
            ->set('label', 'LW Empty Select')
            ->set('dataType', 'select')
            ->set('optionsInput', ' , ')
            ->call('createField')
            ->assertHasErrors('optionsInput');

        Livewire::test(CustomFieldManager::class)
            ->set('entityType', 'lead')
            ->set('fieldKey', 'lw_empty_multi')
            ->set('label', 'LW Empty Multi')
            ->set('dataType', 'multiselect')
            ->set('optionsInput', '')
            ->call('createField')
            ->assertHasErrors('optionsInput');
    }

    public function test_store_and_update_reject_malformed_regex_and_allow_valid_regex_with_commas_colons_and_pipes(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->actingAsOrgMember($admin, $org);

        // Malformed regex on store returns 422
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'bad_code',
            'label' => 'Bad Code',
            'data_type' => 'text',
            'validation_rules' => ['regex:not_a_valid_delimited_regex'],
        ])->assertStatus(422)->assertJsonValidationErrors('validation_rules.0');

        // Valid complex regex containing commas, colons, and pipes succeeds on store
        $createRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'complex_code',
            'label' => 'Complex Code',
            'data_type' => 'text',
            'validation_rules' => ['regex:/^(AB,CD|EF:12)$/'],
            'is_required' => true,
        ])->assertStatus(201);

        $defId = $createRes->json('data.id');

        // Malformed regex on update returns 422 without overwriting existing rules
        $this->patchJson("/ajax/custom-fields/{$defId}", [
            'validation_rules' => ['regex:/unclosed'],
        ])->assertStatus(422)->assertJsonValidationErrors('validation_rules.0');

        // Runtime validation enforces the stored complex regex
        $validator = app(CustomFieldValidationService::class);
        $this->assertSame(
            ['complex_code' => 'AB,CD'],
            $validator->validate('lead', ['complex_code' => 'AB,CD'], $org->id),
        );
        $this->assertSame(
            ['complex_code' => 'EF:12'],
            $validator->validate('lead', ['complex_code' => 'EF:12'], $org->id),
        );

        try {
            $validator->validate('lead', ['complex_code' => 'NOPE'], $org->id);
            $this->fail('Expected ValidationException for value failing complex regex.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('complex_code', $e->errors());
        }

        // Livewire CustomFieldManager rejects malformed regex rule
        Livewire::test(CustomFieldManager::class)
            ->set('entityType', 'lead')
            ->set('fieldKey', 'lw_bad_regex')
            ->set('label', 'LW Bad Regex')
            ->set('dataType', 'text')
            ->set('validationRules', ['regex:bad'])
            ->call('createField')
            ->assertHasErrors('validationRules.0');
    }
}
