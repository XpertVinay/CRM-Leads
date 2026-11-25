<?php

namespace Tests\Unit\CustomFields;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\CreatesTestCrmEntityTable;
use Tests\Support\TestAccountStub;
use Tests\Support\TestContactStub;
use Tests\Support\TestDealStub;
use Tests\Support\TestLeadStub;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class HasCustomFieldsTraitTest extends TestCase
{
    use CreatesTestCrmEntityTable;
    use RefreshDatabase;
    use WithTenancyHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestCrmEntityTable();
    }

    protected function tearDown(): void
    {
        $this->dropTestCrmEntityTable();
        parent::tearDown();
    }

    public function test_trait_helpers_and_json_query_work_across_all_four_entities(): void
    {
        $org1 = $this->createOrgWithUser()['organization'];
        $org2 = $this->createOrgWithUser()['organization'];

        $this->withTenant($org1);

        $entities = [
            'lead' => TestLeadStub::class,
            'contact' => TestContactStub::class,
            'account' => TestAccountStub::class,
            'deal' => TestDealStub::class,
        ];

        foreach ($entities as $entityType => $modelClass) {
            CustomFieldDefinition::create([
                'entity_type' => $entityType,
                'field_key' => 'industry',
                'label' => 'Industry',
                'data_type' => 'select',
                'options' => ['Fintech', 'Healthcare'],
                'is_required' => true,
            ]);

            $model = new $modelClass([
                'entity_type' => $entityType,
                'name' => ucfirst($entityType).' Record',
            ]);
            $model->validateAndFillCustomFields(['industry' => 'Fintech']);
            $model->save();

            $this->assertSame('Fintech', $model->fresh()->getCustomField('industry'));

            $model->setCustomField('segment', 'Enterprise')->save();
            $this->assertSame('Enterprise', $model->fresh()->getCustomField('segment'));

            $found = $modelClass::query()
                ->where('entity_type', $entityType)
                ->whereCustomField('industry', 'Fintech')
                ->count();
            $this->assertSame(1, $found);
        }

        // Cross-org isolation on whereCustomField
        $this->withTenant($org2);
        $this->assertSame(0, TestLeadStub::query()->whereCustomField('industry', 'Fintech')->count());
    }

    public function test_validate_and_fill_rejects_invalid_option_and_scope_guards_invalid_key(): void
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

        $lead = new TestLeadStub(['entity_type' => 'lead', 'name' => 'Bad Lead']);

        try {
            $lead->validateAndFillCustomFields(['industry' => 'Automotive']);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('industry', $e->errors());
        }

        $this->expectException(InvalidArgumentException::class);
        TestLeadStub::query()->whereCustomField('industry; DROP TABLE users', 'Fintech')->get();
    }
}
