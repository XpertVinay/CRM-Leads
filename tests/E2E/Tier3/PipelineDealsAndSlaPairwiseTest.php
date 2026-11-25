<?php

namespace Tests\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class PipelineDealsAndSlaPairwiseTest extends E2ETestCase
{
    public function test_t3_p11_multi_pipeline_stage_reordering_deletion_guard_and_cross_pipeline_transition_guard(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P11 Pipeline Guard Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $this->actingAsInOrganization($manager, $org->id);

        $defaultPipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $defaultOpenStageId = (int) DB::table('pipeline_stages')
            ->where('pipeline_id', $defaultPipelineId)
            ->where('category', 'open')
            ->value('id');

        // Create secondary Enterprise pipeline & custom stages
        $pipeRes = $this->postJson('/ajax/pipelines', ['name' => 'Enterprise']);
        $this->assertStatusIn($pipeRes, [200, 201]);
        $enterpriseId = (int) $this->extractJsonData($pipeRes)['id'];

        $s1Id = (int) $this->extractJsonData($this->postJson("/ajax/pipelines/{$enterpriseId}/stages", [
            'name' => 'Discovery',
            'order_index' => 1,
            'win_probability' => 20,
            'category' => 'open',
            'stale_days' => 7,
        ])->assertStatus(201))['id'];

        $s2Id = (int) $this->extractJsonData($this->postJson("/ajax/pipelines/{$enterpriseId}/stages", [
            'name' => 'Security Review',
            'order_index' => 2,
            'win_probability' => 60,
            'category' => 'open',
            'stale_days' => 5,
        ])->assertStatus(201))['id'];

        // Reorder stages
        $this->postJson("/ajax/pipelines/{$enterpriseId}/stages/reorder", [
            'stage_ids' => [$s2Id, $s1Id],
        ])->assertStatus(200);

        $orderedIds = DB::table('pipeline_stages')
            ->where('pipeline_id', $enterpriseId)
            ->orderBy('order_index')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $this->assertSame([$s2Id, $s1Id], array_slice($orderedIds, 0, 2));

        // Create deal sitting in Discovery stage
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Enterprise Multi-Pipe Deal',
            'amount' => 75000,
            'currency' => 'USD',
            'pipeline_id' => $enterpriseId,
            'pipeline_stage_id' => $s1Id,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];

        // Deletion guard on stage and pipeline with active deal
        $deleteStageRes = $this->deleteJson("/ajax/pipelines/{$enterpriseId}/stages/{$s1Id}");
        $this->assertStatusIn($deleteStageRes, [409, 422]);
        $this->assertTrue(DB::table('pipeline_stages')->where('id', $s1Id)->exists());

        $deletePipeRes = $this->deleteJson("/ajax/pipelines/{$enterpriseId}");
        $this->assertStatusIn($deletePipeRes, [409, 422]);
        $this->assertTrue(DB::table('pipelines')->where('id', $enterpriseId)->exists());

        // Transitioning deal to a stage from a different pipeline must fail with 422
        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $defaultOpenStageId,
        ])->assertStatus(422);

        $this->assertSame($s1Id, (int) DB::table('deals')->where('id', $dealId)->value('pipeline_stage_id'));
    }

    public function test_t3_p12_deal_won_unlocks_attribution_revenue_while_lost_with_mandatory_reason_records_zero(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P12 Win Attribution Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'P12 Campaign',
            'code' => 'p12-win',
            'destination_url' => 'https://example.test/p12',
            'channel' => 'ad',
            'ad_spend' => 200,
        ])->assertStatus(201);

        $this->get('/l/p12-win')->assertStatus(302);
        $clickToken = DB::table('link_clicks')->where('organization_id', $org->id)->value('click_token');

        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'click_token' => $clickToken,
        ]))->assertStatus(201))['id'];

        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Primary Won Deal',
            'deal_amount' => 1500.00,
        ]);
        $this->assertStatusIn($convertRes, [200, 201]);

        $deal1Id = (int) DB::table('leads')->where('id', $leadId)->value('converted_deal_id');
        $contactId = (int) DB::table('leads')->where('id', $leadId)->value('converted_contact_id');
        $pipelineId = (int) DB::table('deals')->where('id', $deal1Id)->value('pipeline_id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');
        $lostStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'lost')->value('id');

        $deal2Id = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Secondary Lost Deal',
            'amount' => 2000.00,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];

        // Moving to Lost without/whitespace lost_reason must be rejected with 422
        $this->patchJson("/ajax/deals/{$deal2Id}/stage", [
            'pipeline_stage_id' => $lostStageId,
            'lost_reason' => '   ',
        ])->assertStatus(422);

        $this->patchJson("/ajax/deals/{$deal2Id}/stage", [
            'pipeline_stage_id' => $lostStageId,
            'lost_reason' => 'Competitor discount',
        ])->assertStatus(200);

        $this->assertSame('Competitor discount', DB::table('deals')->where('id', $deal2Id)->value('lost_reason'));

        // Before any Won deal, attributed revenue must be 0
        $preWinAttr = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $preWinRevenue = (float) ($preWinAttr['total_attributed_revenue'] ?? $preWinAttr['data']['total_attributed_revenue'] ?? 0);
        $this->assertEqualsWithDelta(0.0, $preWinRevenue, 0.01);

        // Move Deal 1 to Won -> deal_won touchpoint recorded and $1,500 attributed (not $3,500)
        $this->patchJson("/ajax/deals/{$deal1Id}/stage", [
            'pipeline_stage_id' => $wonStageId,
        ])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('deal_id', $deal1Id)
                ->where('event_milestone', 'deal_won')
                ->count()
        );

        $postWinAttr = $this->getJson('/ajax/analytics/attribution?model=first_touch')->assertStatus(200)->json();
        $postWinRevenue = (float) ($postWinAttr['total_attributed_revenue'] ?? $postWinAttr['data']['total_attributed_revenue'] ?? 0);
        $this->assertEqualsWithDelta(1500.00, $postWinRevenue, 0.01);
    }

    public function test_t3_p13_deal_stage_transition_via_ajax_and_livewire_dispatches_stage_changed_notification(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P13 Stage Notify Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStages = DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->get();
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $this->assertGreaterThanOrEqual(2, $openStages->count());

        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Stage Notify Deal',
            'amount' => 8500,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStages[0]->id,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        // Transition via AJAX as sales_manager
        $this->actingAsInOrganization($manager, $org->id)
            ->patchJson("/ajax/deals/{$dealId}/stage", [
                'pipeline_stage_id' => $openStages[1]->id,
            ])
            ->assertStatus(200);

        $this->assertGreaterThanOrEqual(
            1,
            DB::table('notifications')->where('notifiable_id', $rep->id)->count(),
            'Rep must receive DealStageChangedNotification on AJAX stage transition.'
        );

        // Transition to Won via Livewire DealKanbanBoard
        $kanbanClass = $this->livewireClassIfExists('App\\Modules\\Pipeline\\Livewire\\DealKanbanBoard');
        $this->assertNotNull($kanbanClass, 'DealKanbanBoard Livewire component must exist.');

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        \Livewire\Livewire::actingAs($manager)
            ->test($kanbanClass)
            ->call('moveDeal', $dealId, $wonStageId);

        $dealRow = DB::table('deals')->where('id', $dealId)->first();
        $this->assertSame($wonStageId, (int) $dealRow->pipeline_stage_id);
        $this->assertNotNull($dealRow->closed_at);
        $this->assertGreaterThanOrEqual(2, DB::table('notifications')->where('notifiable_id', $rep->id)->count());
    }

    public function test_t3_p14_stale_deal_sla_command_flags_only_aged_open_deals_and_notifies_owner(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'P14 SLA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];

        $pipeRes = $this->postJson('/ajax/pipelines', ['name' => 'SLA Pipe']);
        $this->assertStatusIn($pipeRes, [200, 201]);
        $pipelineId = (int) $this->extractJsonData($pipeRes)['id'];

        $openStageId = (int) $this->extractJsonData($this->postJson("/ajax/pipelines/{$pipelineId}/stages", [
            'name' => 'SLA Open',
            'order_index' => 1,
            'win_probability' => 30,
            'category' => 'open',
            'stale_days' => 5,
        ])->assertStatus(201))['id'];

        $wonStageId = (int) $this->extractJsonData($this->postJson("/ajax/pipelines/{$pipelineId}/stages", [
            'name' => 'SLA Won',
            'order_index' => 2,
            'win_probability' => 100,
            'category' => 'won',
        ])->assertStatus(201))['id'];

        $agedOpenId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Aged Open Deal',
            'amount' => 5000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        $freshOpenId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Fresh Open Deal',
            'amount' => 4000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        $agedWonId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Aged Won Deal',
            'amount' => 9000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        $this->patchJson("/ajax/deals/{$agedWonId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);

        // Clear stage-change notifications so we isolate SLA warnings cleanly
        DB::table('notifications')->where('notifiable_id', $rep->id)->delete();

        DB::table('deals')->where('id', $agedOpenId)->update([
            'stage_entered_at' => now()->subDays(8),
        ]);
        DB::table('deals')->where('id', $freshOpenId)->update([
            'stage_entered_at' => now()->subDay(),
        ]);
        DB::table('deals')->where('id', $agedWonId)->update([
            'stage_entered_at' => now()->subDays(30),
            'closed_at' => now()->subDays(30),
        ]);

        $this->assertSame(0, $this->invokeSlaCommand());

        $this->assertTrue((bool) DB::table('deals')->where('id', $agedOpenId)->value('is_stale'));
        $this->assertFalse((bool) DB::table('deals')->where('id', $freshOpenId)->value('is_stale'));
        $this->assertFalse((bool) DB::table('deals')->where('id', $agedWonId)->value('is_stale'));

        $warningCount = DB::table('notifications')->where('notifiable_id', $rep->id)->count();
        $this->assertSame(1, $warningCount, 'Only the aged open deal owner must receive a single SLA warning.');

        // Repeat SLA execution must be idempotent (no duplicate warning for already-stale deal)
        $this->assertSame(0, $this->invokeSlaCommand());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $rep->id)->count());
    }

    public function test_t3_p15_polymorphic_activities_timeline_and_cross_tenant_subject_isolation(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'P15 Activity Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'P15 Activity Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        // Seed all 4 subjects in Org B
        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadBId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $accountBId = (int) $this->extractJsonData($this->postJson('/ajax/accounts', $this->validAccountPayload())->assertStatus(201))['id'];
        $contactBId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $pipeBId = (int) DB::table('pipelines')->where('organization_id', $orgB->id)->value('id');
        $stageBId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipeBId)->where('category', 'open')->value('id');
        $dealBId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Org B Deal',
            'amount' => 3000,
            'currency' => 'USD',
            'pipeline_id' => $pipeBId,
            'pipeline_stage_id' => $stageBId,
            'contact_id' => $contactBId,
        ])->assertStatus(201))['id'];

        // Seed all 4 subjects in Org A
        $this->actingAsInOrganization($adminA, $orgA->id);
        $leadAId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $accountAId = (int) $this->extractJsonData($this->postJson('/ajax/accounts', $this->validAccountPayload())->assertStatus(201))['id'];
        $contactAId = (int) $this->extractJsonData($this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201))['id'];
        $pipeAId = (int) DB::table('pipelines')->where('organization_id', $orgA->id)->value('id');
        $stageAId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipeAId)->where('category', 'open')->value('id');
        $dealAId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Org A Deal',
            'amount' => 6000,
            'currency' => 'USD',
            'pipeline_id' => $pipeAId,
            'pipeline_stage_id' => $stageAId,
            'contact_id' => $contactAId,
        ])->assertStatus(201))['id'];

        $ownSubjects = [
            ['lead', $leadAId, 'call', 'Discovery call on lead'],
            ['account', $accountAId, 'note', 'Account executive summary'],
            ['contact', $contactAId, 'email', 'Sent onboarding deck'],
            ['deal', $dealAId, 'meeting', 'Pricing negotiation meeting'],
        ];

        foreach ($ownSubjects as [$type, $id, $actType, $desc]) {
            $res = $this->postJson('/ajax/activities', [
                'subject_type' => $type,
                'subject_id' => $id,
                'type' => $actType,
                'description' => $desc,
            ]);
            $this->assertStatusIn($res, [200, 201]);

            $listRes = $this->getJson("/ajax/activities?subject_type={$type}&subject_id={$id}")->assertStatus(200);
            $items = $this->extractJsonData($listRes);
            $this->assertCount(1, $items);
            $this->assertSame($desc, $items[0]['description'] ?? null);
        }

        // Cross-tenant polymorphic injection must be blocked across all 4 subject types
        foreach ([['lead', $leadBId], ['account', $accountBId], ['contact', $contactBId], ['deal', $dealBId]] as [$type, $foreignId]) {
            $blocked = $this->postJson('/ajax/activities', [
                'subject_type' => $type,
                'subject_id' => $foreignId,
                'type' => 'note',
                'description' => 'Cross-tenant leak attempt',
            ]);
            $this->assertStatusIn($blocked, [403, 404, 422]);
        }

        $this->assertSame(0, DB::table('activities')->where('organization_id', $orgB->id)->count());
    }
}
