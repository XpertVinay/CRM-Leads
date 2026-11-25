<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #11: Dual-Mode Lead Conversion.
 */
class F11DualModeLeadConversionTest extends E2ETestCase
{
    public function test_t1_f11_01_converts_b2b_lead_into_account_contact_and_deal(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert B2B Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'first_name' => 'Pepper',
                'last_name' => 'Potts',
                'email' => 'pepper@stark.test',
                'company_name' => 'Stark Industries',
                'job_title' => 'CEO',
            ]))->assertStatus(201)
        )['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Stark Enterprise Rollout',
            'deal_amount' => 45000,
            'deal_currency' => 'USD',
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $this->assertSame('converted', $leadRow->status);
        $this->assertNotNull($leadRow->converted_at);
        $this->assertNotNull($leadRow->converted_account_id);
        $this->assertNotNull($leadRow->converted_contact_id);
        $this->assertNotNull($leadRow->converted_deal_id);

        $accountRow = DB::table('accounts')->where('id', $leadRow->converted_account_id)->first();
        $this->assertSame('Stark Industries', $accountRow->name);
        $this->assertSame((int) $org->id, (int) $accountRow->organization_id);

        $contactRow = DB::table('contacts')->where('id', $leadRow->converted_contact_id)->first();
        $this->assertSame('pepper@stark.test', $contactRow->email);
        $this->assertSame((int) $accountRow->id, (int) $contactRow->account_id);

        $dealRow = DB::table('deals')->where('id', $leadRow->converted_deal_id)->first();
        $this->assertSame('Stark Enterprise Rollout', $dealRow->title);
        $this->assertEquals(45000.00, (float) $dealRow->amount);
        $this->assertSame((int) $accountRow->id, (int) $dealRow->account_id);
        $this->assertSame((int) $contactRow->id, (int) $dealRow->contact_id);
    }

    public function test_t1_f11_02_converts_b2c_lead_into_standalone_contact_with_null_account_id(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert B2C Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
                'first_name' => 'Noah',
                'last_name' => 'Miller',
                'email' => 'noah@consumer.test',
                'location' => 'Seattle, WA',
            ]))->assertStatus(201)
        )['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Noah Annual Subscription',
            'deal_amount' => 1200,
            'deal_currency' => 'USD',
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $this->assertSame(0, DB::table('accounts')->where('organization_id', $org->id)->count());

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $this->assertSame('converted', $leadRow->status);
        $this->assertNull($leadRow->converted_account_id);
        $this->assertNotNull($leadRow->converted_contact_id);
        $this->assertNotNull($leadRow->converted_deal_id);

        $contactRow = DB::table('contacts')->where('id', $leadRow->converted_contact_id)->first();
        $this->assertSame('noah@consumer.test', $contactRow->email);
        $this->assertNull($contactRow->account_id);

        $dealRow = DB::table('deals')->where('id', $leadRow->converted_deal_id)->first();
        $this->assertNull($dealRow->account_id);
        $this->assertSame((int) $contactRow->id, (int) $dealRow->contact_id);
    }

    public function test_t1_f11_03_b2b_conversion_matches_existing_account_and_supports_contact_only_conversion(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Match Account Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $existingAccountId = (int) $this->extractJsonData(
            $this->postJson('/ajax/accounts', $this->validAccountPayload([
                'name' => 'Globex Corporation',
            ]))->assertStatus(201)
        )['id'];

        $lead1Id = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'email' => 'hank@globex.test',
                'company_name' => 'globex corporation',
            ]))->assertStatus(201)
        )['id'];

        $res1 = $this->postJson("/ajax/leads/{$lead1Id}/convert", [
            'create_deal' => false,
        ]);
        $this->assertStatusIn($res1, [200, 201]);

        $lead1Row = DB::table('leads')->where('id', $lead1Id)->first();
        $this->assertSame($existingAccountId, (int) $lead1Row->converted_account_id);
        $this->assertNull($lead1Row->converted_deal_id);
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());

        $lead2Id = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'email' => 'susan@globex.test',
                'company_name' => 'Globex Corporation',
            ]))->assertStatus(201)
        )['id'];

        $res2 = $this->postJson("/ajax/leads/{$lead2Id}/convert", [
            'create_deal' => true,
            'deal_title' => 'Globex Expansion',
            'deal_amount' => 9000,
            'deal_currency' => 'USD',
        ]);
        $this->assertStatusIn($res2, [200, 201]);

        $lead2Row = DB::table('leads')->where('id', $lead2Id)->first();
        $this->assertSame($existingAccountId, (int) $lead2Row->converted_account_id);
        $this->assertSame(1, DB::table('accounts')->where('organization_id', $org->id)->count());
        $this->assertSame(2, DB::table('contacts')->where('account_id', $existingAccountId)->count());
    }

    public function test_t1_f11_04_conversion_links_existing_touchpoints_to_created_contact_and_deal(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Touchpoints Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Conv Link',
                'code' => 'conv-link-1',
                'destination_url' => 'https://example.test/conv',
                'channel' => 'ad',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/conv-link-1')->assertStatus(302);

        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
                'click_token' => $clickToken,
            ]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Attributed Deal',
            'deal_amount' => 2500,
            'deal_currency' => 'USD',
        ])->assertStatus(200);

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $touchpoint = DB::table('touchpoints')
            ->where('lead_id', $leadId)
            ->where('event_milestone', 'lead_captured')
            ->first();

        $this->assertNotNull($touchpoint);
        $this->assertSame((int) $leadRow->converted_contact_id, (int) $touchpoint->contact_id);
        $this->assertSame((int) $leadRow->converted_deal_id, (int) $touchpoint->deal_id);
    }

    public function test_t1_f11_05_conversion_accepts_explicit_pipeline_stage_deal_custom_fields_and_wizard_view(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Convert Custom Pipeline Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'deal',
            'field_key' => 'deployment_model',
            'label' => 'Deployment Model',
            'data_type' => 'select',
            'options' => ['Cloud', 'OnPrem'],
            'is_required' => true,
        ])->assertStatus(201);

        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $leadDetailPage = $this->get("/leads/{$leadId}");
        $leadDetailPage->assertStatus(200);
        $this->assertNoViteAssets($leadDetailPage);

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->value('id');

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Cloud Deal',
            'deal_amount' => 15000,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'deal_custom_fields' => ['deployment_model' => 'Cloud'],
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $leadRow = DB::table('leads')->where('id', $leadId)->first();
        $dealRow = DB::table('deals')->where('id', $leadRow->converted_deal_id)->first();
        $this->assertSame($pipelineId, (int) $dealRow->pipeline_id);
        $this->assertSame($stageId, (int) $dealRow->pipeline_stage_id);
        $customFields = json_decode((string) $dealRow->custom_fields, true);
        $this->assertSame('Cloud', $customFields['deployment_model'] ?? null);
    }
}
