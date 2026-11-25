<?php

namespace Tests\Feature\Pipeline;

use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class ActivityTimelineAndStaleDealTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_activity_logging_and_timeline_retrieval(): void
    {
        ['organization' => $org, 'user' => $rep] = $this->createOrgWithUser(TenantRole::SalesRep->value);
        $this->withTenant($org);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Charlie',
        ]);

        $this->actingAsOrgMember($rep, $org)
            ->postJson(route('ajax.activities.store'), [
                'activitable_type' => 'contact',
                'activitable_id' => $contact->id,
                'action' => 'call',
                'description' => 'Discussed annual contract renewal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.action', 'call');

        $this->actingAsOrgMember($rep, $org)
            ->getJson(route('ajax.activities.index', [
                'activitable_type' => 'contact',
                'activitable_id' => $contact->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Discussed annual contract renewal');
    }

    public function test_stale_deal_sla_command_flags_overdue_deals(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $openStage = $pipeline->stages()->where('category', 'open')->firstOrFail();
        $openStage->update(['stale_days' => 7]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Diana',
        ]);

        $staleDeal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contact->id,
            'title' => 'Old Deal',
            'amount' => 1000,
            'stage_entered_at' => now()->subDays(10),
            'is_stale' => false,
        ]);

        $freshDeal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contact->id,
            'title' => 'Fresh Deal',
            'amount' => 2000,
            'stage_entered_at' => now()->subDays(2),
            'is_stale' => false,
        ]);

        $this->artisan('crm:detect-stale-deals', ['--org' => $org->id])
            ->assertSuccessful();

        $this->assertTrue($staleDeal->fresh()->is_stale);
        $this->assertFalse($freshDeal->fresh()->is_stale);
    }
}
