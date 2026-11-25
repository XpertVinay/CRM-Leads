<?php

namespace Tests\Unit\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTestCrmEntityTable;
use Tests\Support\TestAccountStub;
use Tests\Support\TestContactStub;
use Tests\Support\TestDealStub;
use Tests\Support\TestLeadStub;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class TenantContextAndScopeTest extends TestCase
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

    public function test_tenant_context_lifecycle_and_clear(): void
    {
        $context = app(TenantContext::class);

        $this->assertFalse($context->hasTenant());
        $this->assertNull($context->getTenant());
        $this->assertNull($context->getTenantId());

        $org = Organization::create(['name' => 'Alpha Corp']);
        $context->setTenant($org);

        $this->assertTrue($context->hasTenant());
        $this->assertSame($org->id, $context->getTenantId());
        $this->assertTrue($context->getTenant()->is($org));

        $context->clear();

        $this->assertFalse($context->hasTenant());
        $this->assertNull($context->getTenant());
        $this->assertNull($context->getTenantId());
    }

    public function test_belongs_to_organization_auto_assigns_and_isolates_across_models(): void
    {
        $org1 = $this->createOrgWithUser()['organization'];
        $org2 = $this->createOrgWithUser()['organization'];

        $this->withTenant($org1);

        $def1 = CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'text',
        ]);

        $lead1 = TestLeadStub::create(['entity_type' => 'lead', 'name' => 'Lead One']);
        $contact1 = TestContactStub::create(['entity_type' => 'contact', 'name' => 'Contact One']);
        $account1 = TestAccountStub::create(['entity_type' => 'account', 'name' => 'Account One']);
        $deal1 = TestDealStub::create(['entity_type' => 'deal', 'name' => 'Deal One']);

        $this->assertSame($org1->id, $def1->organization_id);
        $this->assertSame($org1->id, $lead1->organization_id);
        $this->assertSame($org1->id, $contact1->organization_id);
        $this->assertSame($org1->id, $account1->organization_id);
        $this->assertSame($org1->id, $deal1->organization_id);

        $this->withTenant($org2);

        $def2 = CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'text',
        ]);
        $lead2 = TestLeadStub::create(['entity_type' => 'lead', 'name' => 'Lead Two']);

        $this->assertSame([$def2->id], CustomFieldDefinition::query()->pluck('id')->all());
        $this->assertSame([$lead2->id], TestLeadStub::query()->where('entity_type', 'lead')->pluck('id')->all());
        $this->assertNull(TestLeadStub::query()->find($lead1->id));

        // Updates and deletes under Org 2 scope cannot affect Org 1 records
        $updated = TestLeadStub::query()->where('id', $lead1->id)->update(['name' => 'Hacked']);
        $deleted = CustomFieldDefinition::query()->where('id', $def1->id)->delete();

        $this->assertSame(0, $updated);
        $this->assertSame(0, $deleted);

        // withoutGlobalScope returns all organizations' rows
        $this->assertSame(
            2,
            CustomFieldDefinition::withoutGlobalScope(OrganizationScope::class)->count(),
        );

        $this->withTenant($org1);
        $this->assertSame('Lead One', TestLeadStub::query()->findOrFail($lead1->id)->name);
        $this->assertNotNull(CustomFieldDefinition::query()->find($def1->id));
    }

    public function test_user_is_superadmin_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => 'secret1234',
            'is_superadmin' => true,
        ]);

        $this->assertFalse((bool) $user->is_superadmin);
        $this->assertFalse($user->isSuperadmin());
    }
}
