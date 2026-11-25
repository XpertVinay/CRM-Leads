<?php

namespace Tests\Feature\Pipeline;

use App\Modules\Pipeline\Livewire\DealKanbanBoard;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class DealPipelineAndKanbanTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_deal_stage_transitions_lost_reason_and_won_touchpoint(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stages = $pipeline->stages()->orderBy('order_index')->get();
        $openStage = $stages->firstWhere('category', 'open');
        $wonStage = $stages->firstWhere('category', 'won');
        $lostStage = $stages->firstWhere('category', 'lost');

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Alice',
            'email' => 'alice@example.test',
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'deal-promo',
            'name' => 'Deal Promo',
            'destination_url' => 'https://example.test',
            'channel' => 'ad',
        ]);

        $dealResp = $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.deals.store'), [
                'title' => 'Enterprise License',
                'contact_id' => $contact->id,
                'amount' => 15000,
                'currency' => 'USD',
            ])
            ->assertCreated();

        $dealId = $dealResp->json('data.id');

        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link->id,
            'contact_id' => $contact->id,
            'deal_id' => $dealId,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now()->subDay(),
        ]);

        // Moving to Lost without lost_reason fails validation
        $this->actingAsOrgMember($admin, $org)
            ->patchJson(route('ajax.deals.stage', $dealId), [
                'pipeline_stage_id' => $lostStage->id,
            ])
            ->assertUnprocessable();

        // Moving to Won succeeds and records a deal_won touchpoint
        $this->actingAsOrgMember($admin, $org)
            ->patchJson(route('ajax.deals.stage', $dealId), [
                'pipeline_stage_id' => $wonStage->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.pipeline_stage_id', $wonStage->id);

        $this->assertDatabaseHas('touchpoints', [
            'organization_id' => $org->id,
            'deal_id' => $dealId,
            'event_milestone' => 'deal_won',
        ]);

        // Livewire Kanban board renders and can move deal back to open stage
        $this->actingAsOrgMember($admin, $org);
        Livewire::test(DealKanbanBoard::class)
            ->assertSee('Enterprise License')
            ->call('moveToStage', $dealId, $openStage->id);

        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_id' => $openStage->id,
            'closed_at' => null,
        ]);
    }

    public function test_pipeline_creation_and_deletion_guard(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $resp = $this->actingAsOrgMember($admin, $org)
            ->postJson(route('ajax.pipelines.store'), [
                'name' => 'B2C Fast Track',
                'stages' => [
                    ['name' => 'Inquiry', 'category' => 'open', 'stale_days' => 5],
                    ['name' => 'Closed Won', 'category' => 'won'],
                    ['name' => 'Closed Lost', 'category' => 'lost'],
                ],
            ])
            ->assertCreated();

        $pipelineId = $resp->json('data.id');
        $stageId = $resp->json('data.stages.0.id');

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Bob',
        ]);

        $deal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'contact_id' => $contact->id,
            'title' => 'Active Deal',
            'amount' => 500,
        ]);

        // Cannot delete pipeline with active deals
        $this->actingAsOrgMember($admin, $org)
            ->deleteJson(route('ajax.pipelines.destroy', $pipelineId))
            ->assertUnprocessable();

        $deal->forceDelete();

        // Now deletion succeeds
        $this->actingAsOrgMember($admin, $org)
            ->deleteJson(route('ajax.pipelines.destroy', $pipelineId))
            ->assertOk();
    }
}
