<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #11: Dual-Mode Lead Conversion.
 */
class F11DualModeLeadConversionBoundaryTest extends E2ETestCase
{
    public function test_t2_f11_01_duplicate_conversion_of_already_converted_lead_is_rejected(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Duplicate Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Initial Deal',
            'deal_amount' => 5000,
        ])->assertStatus(200);

        $secondRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => false,
        ]);
        $secondRes->assertStatus(422);

        $this->assertSame(1, DB::table('accounts')->where('organization_id', $org->id)->count());
        $this->assertSame(1, DB::table('contacts')->where('organization_id', $org->id)->count());
        $this->assertSame(1, DB::table('deals')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f11_02_transactional_rollback_when_deal_creation_fails_on_invalid_custom_field(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Rollback CF Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'contract_code',
            'label' => 'Contract Code',
            'data_type' => 'text',
            'is_required' => true,
        ])->assertStatus(201);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'company_name' => 'Rollback Systems',
            ]))->assertStatus(201)
        )['id'];

        $failedConvert = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Rollback Deal',
            'deal_amount' => 8000,
        ]);
        $failedConvert->assertStatus(422);

        $this->assertSame(0, DB::table('accounts')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('contacts')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $this->assertNotSame('converted', $leadRow->status);
        $this->assertNull($leadRow->converted_account_id);
        $this->assertNull($leadRow->converted_contact_id);
        $this->assertNull($leadRow->converted_deal_id);
    }

    public function test_t2_f11_03_missing_deal_title_or_negative_amount_when_create_deal_true_rolls_back(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Rollback Deal Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload())->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => '',
            'deal_amount' => -250,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('contacts')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
        $this->assertNotSame('converted', DB::table('leads')->where('id', $leadId)->value('status'));
    }

    public function test_t2_f11_04_cross_organization_lead_account_and_pipeline_rejected_atomically(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];
        $accountBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/accounts', $this->validAccountPayload())->assertStatus(201)
        )['id'];
        $pipelineBId = (int) DB::table('pipelines')->where('organization_id', $orgB->id)->value('id');
        $stageBId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineBId)->where('category', 'open')->value('id');

        $this->actingAsInOrganization($adminA, $orgA->id);
        $leadAId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->assertStatusIn(
            $this->postJson("/ajax/leads/{$leadBId}/convert", ['create_deal' => false]),
            [403, 404]
        );

        $this->assertStatusIn(
            $this->postJson("/ajax/leads/{$leadAId}/convert", [
                'create_deal' => false,
                'account_id' => $accountBId,
            ]),
            [403, 404, 422]
        );

        $this->assertStatusIn(
            $this->postJson("/ajax/leads/{$leadAId}/convert", [
                'create_deal' => true,
                'deal_title' => 'Hijacked Pipeline Deal',
                'deal_amount' => 1000,
                'pipeline_id' => $pipelineBId,
                'pipeline_stage_id' => $stageBId,
            ]),
            [403, 404, 422]
        );

        $this->assertSame(0, DB::table('contacts')->where('organization_id', $orgA->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $orgA->id)->count());
    }

    public function test_t2_f11_05_read_only_and_peer_sales_rep_cannot_convert_lead(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert RBAC Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $rep1 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $rep2 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'assigned_user_id' => $rep2->id,
            ]))->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($readOnly, $org->id)
            ->postJson("/ajax/leads/{$leadId}/convert", ['create_deal' => false])
            ->assertStatus(403);

        $this->actingAsInOrganization($rep1, $org->id)
            ->postJson("/ajax/leads/{$leadId}/convert", ['create_deal' => false])
            ->assertStatus(403);

        $this->assertNotSame('converted', DB::table('leads')->where('id', $leadId)->value('status'));
        $this->assertSame(0, DB::table('contacts')->where('organization_id', $org->id)->count());
    }
}
