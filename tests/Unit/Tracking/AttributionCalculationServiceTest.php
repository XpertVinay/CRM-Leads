<?php

namespace Tests\Unit\Tracking;

use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class AttributionCalculationServiceTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_non_won_deals_produce_zero_attributed_revenue(): void
    {
        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $pipeline = Pipeline::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $openStage = PipelineStage::where('pipeline_id', $pipeline->id)->where('category', 'open')->firstOrFail();
        $lostStage = PipelineStage::where('pipeline_id', $pipeline->id)->where('category', 'lost')->firstOrFail();

        $link = app(LinkShortenerService::class)->create($org, [
            'name' => 'Ad 1',
            'destination_url' => 'https://example.test/1',
            'channel' => 'ad',
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Open',
            'last_name' => 'Prospect',
        ]);

        $openDeal = Deal::create([
            'organization_id' => $org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contact->id,
            'title' => 'Open Deal',
            'amount' => 5000.00,
            'currency' => 'USD',
        ]);

        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link->id,
            'contact_id' => $contact->id,
            'deal_id' => $openDeal->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now()->subDay(),
        ]);

        $service = app(AttributionCalculationService::class);
        $this->assertSame([], $service->attributeDeal($openDeal, 'first_touch'));

        $openDeal->update(['pipeline_stage_id' => $lostStage->id, 'lost_reason' => 'Budget']);
        $this->assertSame([], $service->attributeDeal($openDeal->fresh(), 'linear'));
    }

    public function test_first_touch_last_touch_and_exact_sum_linear_attribution_on_won_deal(): void
    {
        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $shortener = app(LinkShortenerService::class);
        $link1 = $shortener->create($org, ['name' => 'First', 'destination_url' => 'https://example.test/1', 'channel' => 'ad', 'ad_spend' => 25.00]);
        $link2 = $shortener->create($org, ['name' => 'Second', 'destination_url' => 'https://example.test/2', 'channel' => 'social', 'ad_spend' => 10.00]);
        $link3 = $shortener->create($org, ['name' => 'Third', 'destination_url' => 'https://example.test/3', 'channel' => 'whatsapp', 'ad_spend' => 15.00]);

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2C',
            'status' => 'new',
            'first_name' => 'Maya',
            'email' => 'maya@example.test',
        ]);

        $click1 = LinkClick::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link1->id,
            'click_token' => (string) Str::uuid(),
            'ip_hash' => str_repeat('a', 64),
            'clicked_at' => now()->subDays(3),
        ]);

        // Two milestones sharing the same link_click_id should be deduplicated to 1 touchpoint.
        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link1->id,
            'link_click_id' => $click1->id,
            'lead_id' => $lead->id,
            'event_milestone' => 'click',
            'occurred_at' => now()->subDays(3),
        ]);
        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link1->id,
            'link_click_id' => $click1->id,
            'lead_id' => $lead->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now()->subDays(3)->addMinutes(5),
        ]);

        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link2->id,
            'lead_id' => $lead->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now()->subDays(2),
        ]);
        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link3->id,
            'lead_id' => $lead->id,
            'event_milestone' => 'conversation_qualified',
            'occurred_at' => now()->subDay(),
        ]);

        $pipeline = Pipeline::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $wonStage = PipelineStage::where('pipeline_id', $pipeline->id)->where('category', 'won')->firstOrFail();

        $converted = app(LeadConversionService::class)->convert($lead, LeadConversionData::fromArray([
            'create_deal' => true,
            'deal_title' => 'Maya Won Deal',
            'deal_amount' => 100.00,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $wonStage->id,
        ]));

        /** @var Deal $deal */
        $deal = $converted['deal'];

        // A deal_won touchpoint should be excluded when pre-deal touchpoints exist.
        Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link3->id,
            'deal_id' => $deal->id,
            'contact_id' => $converted['contact']->id,
            'event_milestone' => 'deal_won',
            'occurred_at' => now(),
        ]);

        $attribution = app(AttributionCalculationService::class);

        $first = $attribution->attributeDeal($deal, 'first_touch');
        $this->assertCount(1, $first);
        $this->assertSame((int) $link1->id, $first[0]['tracking_link_id']);
        $this->assertSame(10000, $first[0]['cents']);

        $last = $attribution->attributeDeal($deal, 'last_touch');
        $this->assertCount(1, $last);
        $this->assertSame((int) $link3->id, $last[0]['tracking_link_id']);
        $this->assertSame(10000, $last[0]['cents']);

        $linear = $attribution->attributeDeal($deal, 'linear');
        $this->assertCount(3, $linear);
        $this->assertSame([3334, 3333, 3333], array_column($linear, 'cents'));
        $this->assertSame([33.34, 33.33, 33.33], array_column($linear, 'amount'));
        $this->assertSame(10000, array_sum(array_column($linear, 'cents')));

        $summary = collect($attribution->aggregateByTrackingLink((int) $org->id, 'first_touch'))->keyBy('tracking_link_id');
        $this->assertSame(100.0, $summary[$link1->id]['attributed_revenue']);
        $this->assertSame(4.0, $summary[$link1->id]['roas']);
        $this->assertSame(0.0, $summary[$link2->id]['attributed_revenue']);
    }
}
