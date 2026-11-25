<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #12: Event-Driven Attribution Engine.
 */
class F12AttributionEngineTest extends E2ETestCase
{
    public function test_t1_f12_01_four_lifecycle_milestones_remain_distinct_and_non_interchangeable(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Attribution Milestones Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Milestone Link',
                'code' => 'milestone-1',
                'destination_url' => 'https://example.test/m1',
                'channel' => 'ad',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/milestone-1')->assertStatus(302);

        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');
        $this->assertSame(1, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'click')->count());
        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'lead_captured')->count());

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'click_token' => $clickToken,
            ]))->assertStatus(201)
        )['id'];

        $this->assertSame(1, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'lead_captured')->count());
        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'deal_won')->count());

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Milestone Deal',
            'deal_amount' => 10000,
            'deal_currency' => 'USD',
        ])->assertStatus(200);

        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $this->assertSame(0, DB::table('touchpoints')->where('organization_id', $org->id)->where('event_milestone', 'deal_won')->count());

        $openAttrRes = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $this->extractTotalAttributedRevenue($openAttrRes->json()), 0.001);

        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'won')
            ->value('id');

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('deal_id', $dealId)
                ->where('event_milestone', 'deal_won')
                ->count()
        );
    }

    public function test_t1_f12_02_first_touch_attributes_100_percent_to_earliest_touchpoint(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'First Touch Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $linkAId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'First Touch Ad',
                'code' => 'ft-ad',
                'destination_url' => 'https://example.test/ft',
                'channel' => 'ad',
                'ad_spend' => 500,
            ])->assertStatus(201)
        )['id'];

        $linkBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Later Email',
                'code' => 'ft-email',
                'destination_url' => 'https://example.test/email',
                'channel' => 'email',
                'ad_spend' => 200,
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/ft-ad')->assertStatus(302);
        $this->get('/l/ft-email')->assertStatus(302);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $dealId = (int) $this->extractJsonData(
            $this->postJson('/ajax/deals', [
                'title' => 'First Touch Deal',
                'amount' => 10000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $openStageId,
                'contact_id' => $contactId,
            ])->assertStatus(201)
        )['id'];

        DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkAId)
            ->update([
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'occurred_at' => now()->subHours(5),
            ]);

        DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkBId)
            ->update([
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'occurred_at' => now()->subHour(),
            ]);

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $payload = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();

        $this->assertEqualsWithDelta(10000.00, $this->extractLinkAttributedRevenue($payload, $linkAId), 0.01);
        $this->assertEqualsWithDelta(0.00, $this->extractLinkAttributedRevenue($payload, $linkBId), 0.01);
    }

    public function test_t1_f12_03_last_touch_attributes_100_percent_to_latest_touchpoint(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Last Touch Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $linkAId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Early Social',
                'code' => 'lt-social',
                'destination_url' => 'https://example.test/s',
                'channel' => 'social',
            ])->assertStatus(201)
        )['id'];

        $linkBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Closing Email',
                'code' => 'lt-email',
                'destination_url' => 'https://example.test/e',
                'channel' => 'email',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/lt-social')->assertStatus(302);
        $this->get('/l/lt-email')->assertStatus(302);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $dealId = (int) $this->extractJsonData(
            $this->postJson('/ajax/deals', [
                'title' => 'Last Touch Deal',
                'amount' => 10000,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $openStageId,
                'contact_id' => $contactId,
            ])->assertStatus(201)
        )['id'];

        DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkAId)
            ->update([
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'occurred_at' => now()->subHours(6),
            ]);

        DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkBId)
            ->update([
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'occurred_at' => now()->subMinutes(30),
            ]);

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $payload = $this->getJson('/ajax/analytics/attribution?model=last_touch')->assertStatus(200)->json();

        $this->assertEqualsWithDelta(0.00, $this->extractLinkAttributedRevenue($payload, $linkAId), 0.01);
        $this->assertEqualsWithDelta(10000.00, $this->extractLinkAttributedRevenue($payload, $linkBId), 0.01);
    }

    public function test_t1_f12_04_linear_attribution_distributes_exact_cents_with_zero_rounding_drift(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Linear Exact Cents Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $linkIds = [];
        foreach (['lin-social' => 'social', 'lin-email' => 'email', 'lin-wa' => 'whatsapp'] as $code => $channel) {
            $linkIds[] = (int) $this->extractJsonData(
                $this->postJson('/ajax/tracking-links', [
                    'name' => "Linear {$channel}",
                    'code' => $code,
                    'destination_url' => "https://example.test/{$code}",
                    'channel' => $channel,
                ])->assertStatus(201)
            )['id'];
        }

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        foreach (['lin-social', 'lin-email', 'lin-wa'] as $code) {
            $this->get("/l/{$code}")->assertStatus(302);
        }

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $dealId = (int) $this->extractJsonData(
            $this->postJson('/ajax/deals', [
                'title' => 'Linear 100 Dollar Deal',
                'amount' => 100.00,
                'currency' => 'USD',
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $openStageId,
                'contact_id' => $contactId,
            ])->assertStatus(201)
        )['id'];

        foreach ($linkIds as $idx => $linkId) {
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('tracking_link_id', $linkId)
                ->update([
                    'contact_id' => $contactId,
                    'deal_id' => $dealId,
                    'occurred_at' => now()->subHours(4 - $idx),
                ]);
        }

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $payload = $this->getJson('/ajax/analytics/attribution?model=linear')->assertStatus(200)->json();

        $centsPerLink = array_map(
            fn (int $linkId): int => (int) round($this->extractLinkAttributedRevenue($payload, $linkId) * 100),
            $linkIds
        );

        foreach ($centsPerLink as $cents) {
            $this->assertTrue(in_array($cents, [3333, 3334], true), "Expected 3333 or 3334 cents, got {$cents}.");
        }

        $this->assertSame(10000, array_sum($centsPerLink), 'Linear attribution cents must sum to exact 10000 cents.');
    }

    public function test_t1_f12_05_won_stage_transition_records_deal_won_touchpoint_and_unlocks_revenue(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Won Unlock Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Won Campaign',
                'code' => 'won-unlock-1',
                'destination_url' => 'https://example.test/won',
                'channel' => 'ad',
                'ad_spend' => 200,
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);
        $this->get('/l/won-unlock-1')->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->where('tracking_link_id', $linkId)->value('click_token');

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
                'click_token' => $clickToken,
            ]))->assertStatus(201)
        )['id'];

        $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Unlock Deal',
            'deal_amount' => 4000.00,
            'deal_currency' => 'USD',
        ])->assertStatus(200);

        $dealId = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $pipelineId = (int) DB::table('deals')->where('id', $dealId)->value('pipeline_id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $beforeWon = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $this->assertEqualsWithDelta(0.00, $this->extractLinkAttributedRevenue($beforeWon, $linkId), 0.001);

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('deal_id', $dealId)
                ->where('event_milestone', 'deal_won')
                ->count()
        );

        $afterWon = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $this->assertEqualsWithDelta(4000.00, $this->extractLinkAttributedRevenue($afterWon, $linkId), 0.01);
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

        $sum = 0.0;
        foreach ($this->extractLinkRows($root) as $row) {
            $sum += $this->readRowRevenue($row);
        }

        return $sum;
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function extractLinkAttributedRevenue(array $payload, int $trackingLinkId): float
    {
        $root = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        foreach ($this->extractLinkRows($root) as $row) {
            $rowId = (int) ($row['tracking_link_id'] ?? $row['id'] ?? 0);
            if ($rowId === $trackingLinkId) {
                return $this->readRowRevenue($row);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<mixed>  $root
     * @return array<int, array<string, mixed>>
     */
    private function extractLinkRows(array $root): array
    {
        foreach (['by_link', 'links', 'by_tracking_link', 'campaigns', 'attribution', 'items', 'rows'] as $listKey) {
            if (isset($root[$listKey]) && is_array($root[$listKey])) {
                return array_values(array_filter($root[$listKey], 'is_array'));
            }
        }

        if (array_is_list($root)) {
            return array_values(array_filter($root, 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function readRowRevenue(array $row): float
    {
        foreach (['attributed_revenue', 'revenue', 'attributed_amount', 'won_revenue'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }

        if (isset($row['attributed_cents']) && is_numeric($row['attributed_cents'])) {
            return ((float) $row['attributed_cents']) / 100.0;
        }

        return 0.0;
    }
}
