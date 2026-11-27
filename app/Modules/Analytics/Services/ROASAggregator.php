<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;

class ROASAggregator
{
    public function __construct(
        private readonly AttributionCalculationService $attributionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregate(string $model = 'last_touch'): array
    {
        $links = TrackingLink::orderByDesc('id')->get();
        $wonDeals = Deal::whereHas('pipelineStage', fn($q) => $q->where('category', 'won'))->get();

        $attributedRevenueByLink = [];
        foreach ($wonDeals as $deal) {
            $allocations = $this->attributionService->attributeDeal($deal, $model);
            foreach ($allocations as $allocation) {
                $linkId = (int) $allocation['tracking_link_id'];
                $attributedRevenueByLink[$linkId] = ($attributedRevenueByLink[$linkId] ?? 0.0) + (float) $allocation['amount'];
            }
        }

        $campaigns = [];
        $totals = [
            'clicks' => 0,
            'leads' => 0,
            'qualified_conversations' => 0,
            'won_deals' => 0,
            'revenue' => 0.0,
            'ad_spend' => 0.0,
        ];

        $linkIds = $links->pluck('id')->all();

        $clicksByLink = $linkIds !== []
            ? LinkClick::query()
                ->whereIn('tracking_link_id', $linkIds)
                ->groupBy('tracking_link_id')
                ->selectRaw('tracking_link_id, COUNT(*) as cnt')
                ->pluck('cnt', 'tracking_link_id')
            : collect();

        $touchpointsByLink = $linkIds !== []
            ? Touchpoint::query()
                ->whereIn('tracking_link_id', $linkIds)
                ->whereIn('event_milestone', ['lead_captured', 'conversation_qualified', 'deal_won'])
                ->groupBy('tracking_link_id', 'event_milestone')
                ->selectRaw('tracking_link_id, event_milestone, COUNT(*) as cnt')
                ->get()
                ->groupBy('tracking_link_id')
            : collect();

        foreach ($links as $link) {
            $clicks = (int) ($clicksByLink->get($link->id) ?? 0);
            $linkTouchpoints = $touchpointsByLink->get($link->id, collect())->keyBy('event_milestone');
            $leads = (int) ($linkTouchpoints->get('lead_captured')?->cnt ?? 0);
            $qualified = (int) ($linkTouchpoints->get('conversation_qualified')?->cnt ?? 0);
            $won = (int) ($linkTouchpoints->get('deal_won')?->cnt ?? 0);

            $revenue = round((float) ($attributedRevenueByLink[$link->id] ?? 0.0), 2);
            $adSpend = round((float) ($link->ad_spend ?? 0.0), 2);
            $cpa = ($leads > 0 && $adSpend > 0) ? round($adSpend / $leads, 2) : 0.0;
            $roas = $adSpend > 0 ? round($revenue / $adSpend, 2) : 0.0;

            $campaigns[] = [
                'tracking_link_id' => $link->id,
                'name' => $link->name,
                'code' => $link->code,
                'channel' => $link->channel,
                'utm_source' => $link->utm_source,
                'clicks' => $clicks,
                'leads' => $leads,
                'qualified_conversations' => $qualified,
                'won_deals' => $won,
                'revenue' => $revenue,
                'ad_spend' => $adSpend,
                'cpa' => $cpa,
                'roas' => $roas,
            ];

            $totals['clicks'] += $clicks;
            $totals['leads'] += $leads;
            $totals['qualified_conversations'] += $qualified;
            $totals['won_deals'] += $won;
            $totals['revenue'] += $revenue;
            $totals['ad_spend'] += $adSpend;
        }

        $totals['revenue'] = round($totals['revenue'], 2);
        $totals['ad_spend'] = round($totals['ad_spend'], 2);
        $totals['cpa'] = ($totals['leads'] > 0 && $totals['ad_spend'] > 0)
            ? round($totals['ad_spend'] / $totals['leads'], 2)
            : 0.0;
        $totals['roas'] = $totals['ad_spend'] > 0
            ? round($totals['revenue'] / $totals['ad_spend'], 2)
            : 0.0;

        return [
            'model' => $model,
            'totals' => $totals,
            'campaigns' => $campaigns,
        ];
    }
}
