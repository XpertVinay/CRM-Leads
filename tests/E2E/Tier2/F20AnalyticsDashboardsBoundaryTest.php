<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F20AnalyticsDashboardsBoundaryTest extends E2ETestCase
{
    public function test_t2_f20_01_analytics_endpoints_are_strictly_tenant_isolated(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Analytics Iso A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Analytics Iso B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];
        $secretRepB = $this->createMemberInOrganization($orgB->id, 'sales_rep', ['name' => 'SecretOrgBRep'])['user'];

        // Seed high-value Org B deal + tracking link + WA
        $this->actingAsInOrganization($adminB, $orgB->id);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'SecretOrgBCampaign',
            'code' => 'secret-org-b-20',
            'destination_url' => 'https://example.test/b',
            'channel' => 'ad',
            'ad_spend' => 777,
        ])->assertStatusIn([200, 201]);
        $this->get('/l/secret-org-b-20')->assertStatus(302);
        $clickTokenB = (string) DB::table('link_clicks')->orderByDesc('id')->value('click_token');
        $leadBId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'assigned_user_id' => $secretRepB->id,
            'click_token' => $clickTokenB,
        ]))->assertStatus(201))['id'];
        $pipeBId = (int) DB::table('pipelines')->where('organization_id', $orgB->id)->value('id');
        $openBId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipeBId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonBId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipeBId)->where('category', 'won')->value('id');
        $this->postJson("/ajax/leads/{$leadBId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Org B Mega Deal',
            'deal_amount' => 999000,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipeBId,
            'pipeline_stage_id' => $openBId,
        ])->assertStatusIn([200, 201]);
        $dealBId = (int) DB::table('deals')->where('organization_id', $orgB->id)->value('id');
        $this->patchJson("/ajax/deals/{$dealBId}/stage", ['pipeline_stage_id' => $wonBId])->assertStatus(200);

        // Query from Org A
        $this->actingAsInOrganization($adminA, $orgA->id);

        foreach (['executive', 'attribution', 'productivity', 'whatsapp'] as $dash) {
            $res = $this->getJson("/ajax/analytics/{$dash}");
            $res->assertStatus(200);
            $raw = (string) $res->getContent();
            $this->assertStringNotContainsString('999000', $raw);
            $this->assertStringNotContainsString('SecretOrgBRep', $raw);
            $this->assertStringNotContainsString('SecretOrgBCampaign', $raw);
        }
    }

    public function test_t2_f20_02_zero_division_safety_when_ad_spend_closed_deals_and_messages_are_zero(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Zero Division Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Zero Spend Link',
            'code' => 'zero-spend-2002',
            'destination_url' => 'https://example.test/zero',
            'channel' => 'organic',
            'ad_spend' => 0,
        ])->assertStatusIn([200, 201]);

        foreach (['executive', 'attribution', 'productivity', 'whatsapp'] as $dash) {
            $this->getJson("/ajax/analytics/{$dash}")->assertStatus(200);
            $this->get("/analytics/{$dash}")->assertStatus(200);
        }
    }

    public function test_t2_f20_03_non_whatsapp_org_receives_zeroed_whatsapp_health_stats_with_200(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'NoWA Health Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $htmlRes = $this->get('/analytics/whatsapp');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        $jsonRes = $this->getJson('/ajax/analytics/whatsapp');
        $jsonRes->assertStatus(200);
        $data = $this->extractJsonData($jsonRes);
        $this->assertIsArray($data);
    }

    public function test_t2_f20_04_invalid_attribution_model_or_inverted_date_range_returns_422(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Invalid Filter Analytics Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->getJson('/ajax/analytics/attribution?model=non_existent_model')->assertStatus(422);
        $this->getJson('/ajax/analytics/executive?date_from=2026-12-31&date_to=2026-01-01')->assertStatus(422);
    }

    public function test_t2_f20_05_read_only_user_allowed_200_on_all_four_analytics_dashboards(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'ReadOnly Analytics Org']);
        $org = $tenant['organization'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($readOnly, $org->id);

        foreach (['executive', 'attribution', 'productivity', 'whatsapp'] as $dash) {
            $html = $this->get("/analytics/{$dash}");
            $html->assertStatus(200);
            $this->assertNoViteAssets($html);

            $this->getJson("/ajax/analytics/{$dash}")->assertStatus(200);
        }
    }
}
