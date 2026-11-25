<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #12: Event-Driven Attribution Engine.
 */
class F12AttributionEngineBoundaryTest extends E2ETestCase
{
    public function test_t2_f12_01_fifty_clicks_leads_and_open_or_lost_deals_attribute_zero_revenue(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Zero Revenue Invariant Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Black Friday Zero Conv',
                'code' => 'bf-zero-conv',
                'destination_url' => 'https://example.test/bf',
                'channel' => 'ad',
                'ad_spend' => 1000,
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        for ($i = 0; $i < 50; $i++) {
            $this->get('/l/bf-zero-conv')->assertStatus(302);
        }

        $this->assertSame(50, DB::table('link_clicks')->where('tracking_link_id', $linkId)->count());
        $tokens = DB::table('link_clicks')->where('tracking_link_id', $linkId)->limit(2)->pluck('click_token');

        $this->actingAsInOrganization($admin, $org->id);

        $lead1Id = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['click_token' => (string) $tokens[0]]))->assertStatus(201)
        )['id'];
        $lead2Id = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload(['click_token' => (string) $tokens[1]]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$lead1Id}/convert", [
            'create_deal' => true,
            'deal_title' => 'Open Deal Only',
            'deal_amount' => 5000,
        ])->assertStatus(200);

        $this->postJson("/ajax/leads/{$lead2Id}/convert", [
            'create_deal' => true,
            'deal_title' => 'Lost Deal Only',
            'deal_amount' => 7500,
        ])->assertStatus(200);

        $lostDealId = (int) DB::table('leads')->where('id', $lead2Id)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $lostDealId)->value('pipeline_id');
        $lostStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'lost')->value('id');

        $this->patchJson("/ajax/deals/{$lostDealId}/stage", [
            'pipeline_stage_id' => $lostStageId,
            'lost_reason' => 'Price too high',
        ])->assertStatus(200);

        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'deal_won')->count());

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $payload = $this->getJson("/ajax/analytics/attribution?model={$model}")->assertStatus(200)->json();
            $this->assertEqualsWithDelta(0.00, $this->extractTotalAttributedRevenue($payload), 0.001, "Model {$model} must report $0.00 without Won deals.");
        }
    }

    public function test_t2_f12_02_lost_deal_never_attributes_revenue_across_any_model(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lost Zero Attr Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Lost Test Link',
                'code' => 'lost-zero-1',
                'destination_url' => 'https://example.test/lost',
                'channel' => 'email',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/lost-zero-1')->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['click_token' => $clickToken]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Lost Prospect',
            'deal_amount' => 12000,
        ])->assertStatus(200);

        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $lostStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'lost')->value('id');

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $lostStageId,
            'lost_reason' => 'Chose competitor',
        ])->assertStatus(200);

        $this->assertSame(0, DB::table('touchpoints')->where('deal_id', $dealId)->where('event_milestone', 'deal_won')->count());

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $payload = $this->getJson("/ajax/analytics/attribution?model={$model}")->assertStatus(200)->json();
            $this->assertEqualsWithDelta(0.00, $this->extractTotalAttributedRevenue($payload), 0.001);
        }
    }

    public function test_t2_f12_03_reopening_won_deal_to_open_removes_won_attributed_revenue(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Reopen Won Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Reopen Link',
                'code' => 'reopen-won-1',
                'destination_url' => 'https://example.test/reopen',
                'channel' => 'social',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/reopen-won-1')->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['click_token' => $clickToken]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Reopened Deal',
            'deal_amount' => 8000,
        ])->assertStatus(200);

        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        $wonPayload = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $this->assertEqualsWithDelta(8000.00, $this->extractTotalAttributedRevenue($wonPayload), 0.01);

        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $openStageId])->assertStatus(200);
        $this->assertNull(DB::table('deals')->where('id', $dealId)->value('closed_at'));

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $reopenedPayload = $this->getJson("/ajax/analytics/attribution?model={$model}")->assertStatus(200)->json();
            $this->assertEqualsWithDelta(0.00, $this->extractTotalAttributedRevenue($reopenedPayload), 0.001);
        }
    }

    public function test_t2_f12_04_won_deal_with_zero_touchpoints_is_handled_safely_without_error(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Zero Touchpoint Won Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $dealId = (int) $this->extractJsonData(
            $this->postJson('/ajax/deals', [
                'title' => 'Direct Inbound Won Deal',
                'amount' => 3000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $openStageId,
                'contact_id' => $contactId,
            ])->assertStatus(201)
        )['id'];

        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $res = $this->getJson("/ajax/analytics/attribution?model={$model}");
            $res->assertStatus(200);
            $this->assertEqualsWithDelta(0.00, $this->extractLinkSumRevenue($res->json()), 0.001);
        }
    }

    public function test_t2_f12_05_attribution_is_strictly_tenant_isolated_and_rejects_invalid_model(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Attr Iso A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Attr Iso B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->createWonAttributedDeal($adminA, $orgA->id, 'attr-iso-a', 9000.00);
        $this->createWonAttributedDeal($adminB, $orgB->id, 'attr-iso-b', 50000.00);

        $this->actingAsInOrganization($adminA, $orgA->id);

        $payloadA = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $this->assertEqualsWithDelta(9000.00, $this->extractTotalAttributedRevenue($payloadA), 0.01);

        $rawJson = json_encode($payloadA, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('attr-iso-b', $rawJson);

        $this->getJson('/ajax/analytics/attribution?model=invalid_model')->assertStatus(422);
    }

    private function createWonAttributedDeal(mixed $admin, int $orgId, string $code, float $amount): void
    {
        $this->actingAsInOrganization($admin, $orgId);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => "Campaign {$code}",
                'code' => $code,
                'destination_url' => "https://example.test/{$code}",
                'channel' => 'ad',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get("/l/{$code}")->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');

        $this->actingAsInOrganization($admin, $orgId);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['click_token' => $clickToken]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => "Deal {$code}",
            'deal_amount' => $amount,
        ])->assertStatus(200);

        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function extractTotalAttributedRevenue(array $payload): float
    {
        $root = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        foreach (['total_attributed_revenue', 'attributed_revenue', 'total_revenue', 'revenue'] as $key) {
            if (isset($root[$key]) && is_numeric($root[$key])) {
                return (float) $root[$key];
            }
        }

        if (isset($root['summary']) && is_array($root['summary'])) {
            foreach (['total_attributed_revenue', 'attributed_revenue', 'total_revenue', 'revenue'] as $key) {
                if (isset($root['summary'][$key]) && is_numeric($root['summary'][$key])) {
                    return (float) $root['summary'][$key];
                }
            }
        }

        return $this->extractLinkSumRevenue($payload);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function extractLinkSumRevenue(array $payload): float
    {
        $root = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $rows = [];

        foreach (['by_link', 'links', 'by_tracking_link', 'campaigns', 'attribution', 'items', 'rows'] as $listKey) {
            if (isset($root[$listKey]) && is_array($root[$listKey])) {
                $rows = array_values(array_filter($root[$listKey], 'is_array'));
                break;
            }
        }

        if ($rows === [] && array_is_list($root)) {
            $rows = array_values(array_filter($root, 'is_array'));
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            foreach (['attributed_revenue', 'revenue', 'attributed_amount', 'won_revenue'] as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $sum += (float) $row[$key];
                    continue 2;
                }
            }
            if (isset($row['attributed_cents']) && is_numeric($row['attributed_cents'])) {
                $sum += ((float) $row['attributed_cents']) / 100.0;
            }
        }

        return $sum;
    }
}
