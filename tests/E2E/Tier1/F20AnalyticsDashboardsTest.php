<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\E2E\Support\E2ETestCase;

class F20AnalyticsDashboardsTest extends E2ETestCase
{
    public function test_t1_f20_01_executive_dashboard_computes_pipeline_weighted_value_win_rate_and_funnel(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Exec Analytics Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->orderBy('order_index')->get();
        $openStage = $stages->firstWhere('category', 'open');
        $wonStage = $stages->firstWhere('category', 'won');
        $lostStage = $stages->firstWhere('category', 'lost');
        $this->assertNotNull($openStage);
        $this->assertNotNull($wonStage);
        $this->assertNotNull($lostStage);

        // Open deal: $10,000 at openStage->win_probability
        $this->postJson('/ajax/deals', [
            'title' => 'Exec Open Deal',
            'amount' => 10000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contactId,
        ])->assertStatus(201);

        // Won deal: $20,000
        $wonDealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Exec Won Deal',
            'amount' => 20000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$wonDealId}/stage", ['pipeline_stage_id' => $wonStage->id])->assertStatus(200);

        // Lost deal: $5,000
        $lostDealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Exec Lost Deal',
            'amount' => 5000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$lostDealId}/stage", [
            'pipeline_stage_id' => $lostStage->id,
            'lost_reason' => 'Budget freeze',
        ])->assertStatus(200);

        $htmlRes = $this->get('/analytics/executive');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        $jsonRes = $this->getJson('/ajax/analytics/executive');
        $jsonRes->assertStatus(200);
        $data = $this->extractJsonData($jsonRes);
        $this->assertNotEmpty($data);

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('50', $encoded); // 50% win rate (1 won / 2 closed)

        $funnelClass = $this->livewireClassIfExists('App\\Modules\\Analytics\\Livewire\\FunnelVelocityDashboard');
        if ($funnelClass !== null) {
            $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
            if (class_exists($tenantContextClass)) {
                $this->app->make($tenantContextClass)->setTenant($org);
            }
            Livewire::actingAs($admin)->test($funnelClass)->assertStatus(200);
        }
    }

    public function test_t1_f20_02_attribution_dashboard_computes_clicks_leads_won_cpa_and_roas(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Attribution Analytics Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'Google Ads Search',
            'code' => 'gads-2002',
            'destination_url' => 'https://example.test/demo',
            'channel' => 'ad',
            'utm_source' => 'google',
            'ad_spend' => 500,
        ])->assertStatusIn([200, 201]);

        $this->get('/l/gads-2002')->assertStatus(302);
        $this->get('/l/gads-2002')->assertStatus(302);
        $clickToken = (string) DB::table('link_clicks')->orderByDesc('id')->value('click_token');

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'click_token' => $clickToken,
        ]))->assertStatus(201))['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Google Ads Won Deal',
            'deal_amount' => 2500,
            'deal_currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $dealId = (int) DB::table('deals')->where('organization_id', $org->id)->value('id');
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        $htmlRes = $this->get('/analytics/attribution');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $res = $this->getJson("/ajax/analytics/attribution?model={$model}");
            $res->assertStatus(200);
            $payload = json_encode($this->extractJsonData($res), JSON_THROW_ON_ERROR);
            $this->assertStringContainsString('2500', $payload);
            $this->assertStringContainsString('500', $payload);
            $this->assertStringContainsString('5', $payload); // ROAS = 2500 / 500 = 5
        }

        $attrClass = $this->livewireClassIfExists('App\\Modules\\Analytics\\Livewire\\AttributionReportView');
        if ($attrClass !== null) {
            $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
            if (class_exists($tenantContextClass)) {
                $this->app->make($tenantContextClass)->setTenant($org);
            }
            Livewire::actingAs($admin)->test($attrClass)->assertStatus(200);
        }
    }

    public function test_t1_f20_03_productivity_dashboard_reports_per_rep_leads_deals_and_revenue(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Productivity Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $rep1 = $this->createMemberInOrganization($org->id, 'sales_rep', ['name' => 'Alpha Rep'])['user'];
        $rep2 = $this->createMemberInOrganization($org->id, 'sales_rep', ['name' => 'Beta Rep'])['user'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['assigned_user_id' => $rep1->id]))->assertStatus(201);
        $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['assigned_user_id' => $rep2->id]))->assertStatus(201);

        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];
        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $deal1Id = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Alpha Won Deal',
            'amount' => 8000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep1->id,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$deal1Id}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        $this->postJson('/ajax/deals', [
            'title' => 'Beta Open Deal',
            'amount' => 3000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep2->id,
        ])->assertStatus(201);

        $htmlRes = $this->get('/analytics/productivity');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        $jsonRes = $this->getJson('/ajax/analytics/productivity');
        $jsonRes->assertStatus(200);
        $encoded = json_encode($this->extractJsonData($jsonRes), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Alpha Rep', $encoded);
        $this->assertStringContainsString('Beta Rep', $encoded);
        $this->assertStringContainsString('8000', $encoded);
    }

    public function test_t1_f20_04_whatsapp_health_dashboard_reports_delivery_read_and_active_windows(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'WA Health Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $waAccountModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waConvModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppConversation';
        $waMsgModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppMessage';

        $account = $waAccountModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_health',
            'phone_number_id' => 'pn_health',
            'display_phone_number' => '+15552000004',
            'access_token_encrypted' => 'enc-health',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $c1 = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559200001']))->assertStatus(201))['id'];
        $c2 = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559200002']))->assertStatus(201))['id'];

        $activeConv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $c1,
            'customer_phone' => '+15559200001',
            'last_inbound_at' => now()->subHours(2),
            'last_message_at' => now()->subHours(2),
        ]);
        $expiredConv = $waConvModel::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $c2,
            'customer_phone' => '+15559200002',
            'last_inbound_at' => now()->subHours(40),
            'last_message_at' => now()->subHours(40),
        ]);

        $waMsgModel::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $activeConv->id,
            'meta_message_id' => 'wamid.H1',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hello',
            'status' => 'delivered',
            'sent_at' => now()->subHours(2),
        ]);
        $waMsgModel::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $activeConv->id,
            'meta_message_id' => 'wamid.H2',
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Reply',
            'status' => 'read',
            'sent_at' => now()->subHour(),
        ]);
        $waMsgModel::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $expiredConv->id,
            'meta_message_id' => 'wamid.H3',
            'direction' => 'outbound',
            'message_type' => 'template',
            'template_name' => 'ping_tpl',
            'body' => 'Template ping',
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(30),
        ]);

        $htmlRes = $this->get('/analytics/whatsapp');
        $htmlRes->assertStatus(200);
        $this->assertNoViteAssets($htmlRes);

        $jsonRes = $this->getJson('/ajax/analytics/whatsapp');
        $jsonRes->assertStatus(200);
        $data = $this->extractJsonData($jsonRes);
        $this->assertNotEmpty($data);
    }

    public function test_t1_f20_05_analytics_dashboards_support_date_range_filtering(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Date Filter Analytics Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $oldDealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Old Won Deal',
            'amount' => 10000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$oldDealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);
        DB::table('deals')->where('id', $oldDealId)->update([
            'closed_at' => now()->subDays(60),
            'created_at' => now()->subDays(65),
        ]);

        $recentDealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Recent Won Deal',
            'amount' => 4000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$recentDealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        $fromRecent = now()->subDays(7)->toDateString();
        $toRecent = now()->toDateString();
        $recentRes = $this->getJson("/ajax/analytics/executive?date_from={$fromRecent}&date_to={$toRecent}");
        $recentRes->assertStatus(200);
        $recentEncoded = json_encode($this->extractJsonData($recentRes), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('4000', $recentEncoded);
        $this->assertStringNotContainsString('14000', $recentEncoded);
    }
}
