<?php

namespace Tests\Unit\CustomFields;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class CustomFieldValidationServiceTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_validates_all_seven_data_types_and_strips_unknown_keys(): void
    {
        $org = $this->createOrgWithUser()['organization'];
        $this->withTenant($org);

        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'nickname',
            'label' => 'Nickname',
            'data_type' => 'text',
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'notes',
            'label' => 'Notes',
            'data_type' => 'textarea',
            'is_required' => false,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'lead_score',
            'label' => 'Lead Score',
            'data_type' => 'number',
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'budget_ratio',
            'label' => 'Budget Ratio',
            'data_type' => 'number',
            'is_required' => false,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'follow_up_date',
            'label' => 'Follow-up Date',
            'data_type' => 'date',
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'is_vip',
            'label' => 'VIP',
            'data_type' => 'boolean',
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'regions',
            'label' => 'Regions',
            'data_type' => 'multiselect',
            'options' => ['NA', 'EMEA', 'APAC'],
            'is_required' => true,
        ]);

        $service = app(CustomFieldValidationService::class);

        $validated = $service->validate('lead', [
            'nickname' => 'Acme Lead',
            'notes' => '',
            'lead_score' => '42',
            'budget_ratio' => '3.5',
            'follow_up_date' => '2026-03-15',
            'is_vip' => false,
            'industry' => 'Fintech',
            'regions' => ['NA', 'EMEA'],
            'injected_unknown' => 'should-be-stripped',
        ], $org->id);

        $this->assertSame([
            'nickname' => 'Acme Lead',
            'notes' => null,
            'lead_score' => 42,
            'budget_ratio' => 3.5,
            'follow_up_date' => '2026-03-15',
            'is_vip' => false,
            'industry' => 'Fintech',
            'regions' => ['NA', 'EMEA'],
        ], $validated);
        $this->assertArrayNotHasKey('injected_unknown', $validated);
    }

    public function test_rejects_invalid_values_across_types_and_required_rules(): void
    {
        $org = $this->createOrgWithUser()['organization'];
        $this->withTenant($org);

        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
            'is_required' => true,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'score',
            'label' => 'Score',
            'data_type' => 'number',
            'is_required' => false,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'close_by',
            'label' => 'Close By',
            'data_type' => 'date',
            'is_required' => false,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'opted_in',
            'label' => 'Opted In',
            'data_type' => 'boolean',
            'is_required' => false,
        ]);
        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'tags',
            'label' => 'Tags',
            'data_type' => 'multiselect',
            'options' => ['Enterprise', 'SMB'],
            'is_required' => true,
        ]);

        $service = app(CustomFieldValidationService::class);

        // Invalid select option
        try {
            $service->validate('lead', ['industry' => 'Automotive', 'tags' => ['Enterprise']], $org->id);
            $this->fail('Expected ValidationException for invalid select option.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('industry', $e->errors());
        }

        // Invalid number
        try {
            $service->validate('lead', ['industry' => 'Fintech', 'tags' => ['Enterprise'], 'score' => 'abc'], $org->id);
            $this->fail('Expected ValidationException for non-numeric score.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('score', $e->errors());
        }

        // Invalid date
        try {
            $service->validate('lead', ['industry' => 'Fintech', 'tags' => ['Enterprise'], 'close_by' => '2026-02-30'], $org->id);
            $this->fail('Expected ValidationException for invalid calendar date.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('close_by', $e->errors());
        }

        // Invalid boolean string
        try {
            $service->validate('lead', ['industry' => 'Fintech', 'tags' => ['Enterprise'], 'opted_in' => 'maybe'], $org->id);
            $this->fail('Expected ValidationException for invalid boolean string.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('opted_in', $e->errors());
        }

        // Invalid multiselect element and empty required multiselect
        try {
            $service->validate('lead', ['industry' => 'Fintech', 'tags' => ['Unknown']], $org->id);
            $this->fail('Expected ValidationException for invalid multiselect option.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags.0', $e->errors());
        }

        try {
            $service->validate('lead', ['industry' => 'Fintech', 'tags' => []], $org->id);
            $this->fail('Expected ValidationException for empty required multiselect.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags', $e->errors());
        }
    }

    public function test_safe_validation_rules_allowlist_and_per_org_entity_isolation(): void
    {
        $org1 = $this->createOrgWithUser()['organization'];
        $org2 = $this->createOrgWithUser()['organization'];

        $this->withTenant($org1);
        $def = CustomFieldDefinition::create([
            'entity_type' => 'contact',
            'field_key' => 'loyalty_tier',
            'label' => 'Loyalty Tier',
            'data_type' => 'text',
            'validation_rules' => ['min:3', 'unique:users,email'],
            'is_required' => true,
        ]);

        $service = app(CustomFieldValidationService::class);
        $registry = app(CustomFieldRegistry::class);

        // Prime cache for Org 1 contact
        $this->assertCount(1, $registry->forEntity('contact', $org1->id));

        // Org 2 has no contact custom fields
        $this->assertSame([], $service->validate('contact', ['loyalty_tier' => 'ab'], $org2->id));

        // Org 1 enforces allowlisted min:3 and ignores unsafe unique:users,email
        try {
            $service->validate('contact', ['loyalty_tier' => 'ab'], $org1->id);
            $this->fail('Expected ValidationException for min:3.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('loyalty_tier', $e->errors());
        }

        $this->assertSame(
            ['loyalty_tier' => 'Gold'],
            $service->validate('contact', ['loyalty_tier' => 'Gold'], $org1->id),
        );

        // Cache invalidation on delete
        $def->delete();
        $this->assertCount(0, $registry->forEntity('contact', $org1->id));

        // All 4 entities supported; unknown entity rejected
        foreach (['lead', 'contact', 'account', 'deal'] as $entity) {
            $this->assertSame([], $service->validate($entity, [], $org1->id));
        }

        $this->expectException(InvalidArgumentException::class);
        $service->validate('invoice', [], $org1->id);
    }

    public function test_guards_against_persisted_malformed_regex_and_validates_complex_regex_delimiters(): void
    {
        $org = $this->createOrgWithUser()['organization'];
        $this->withTenant($org);

        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'legacy_code',
            'label' => 'Legacy Code',
            'data_type' => 'text',
            'validation_rules' => ['regex:bad_pattern', 'min:2'],
            'is_required' => true,
        ]);

        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'complex_code',
            'label' => 'Complex Code',
            'data_type' => 'text',
            'validation_rules' => ['regex:/^(AB,CD|EF:12)$/'],
            'is_required' => false,
        ]);

        $service = app(CustomFieldValidationService::class);

        // Bad persisted regex is dropped safely (no ErrorException) while min:2 still enforces
        try {
            $service->validate('lead', ['legacy_code' => 'A'], $org->id);
            $this->fail('Expected ValidationException for min:2.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('legacy_code', $e->errors());
        }

        $validated = $service->validate('lead', [
            'legacy_code' => 'OK',
            'complex_code' => 'AB,CD',
        ], $org->id);

        $this->assertSame([
            'legacy_code' => 'OK',
            'complex_code' => 'AB,CD',
        ], $validated);

        $this->assertSame(
            ['legacy_code' => 'OK', 'complex_code' => 'EF:12'],
            $service->validate('lead', ['legacy_code' => 'OK', 'complex_code' => 'EF:12'], $org->id),
        );

        try {
            $service->validate('lead', ['legacy_code' => 'OK', 'complex_code' => 'ZZ'], $org->id);
            $this->fail('Expected ValidationException for complex regex mismatch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('complex_code', $e->errors());
        }
    }
}
