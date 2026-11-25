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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function aggregate(string $model = 'last_touch'): array
    {
        $links = TrackingLink::orderByDesc('id')->get();
        $wonDeals = Deal::whereHas('pipelineStage', fn ($q) => $q->where('category', 'won'))->get();

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

        foreach ($links as $link) {
            $clicks = LinkClick::where('tracking_link_id', $link->id)->count();
            $leads = Touchpoint::where('tracking_link_id', $link->id)
                ->where('event_milestone', 'lead_captured')
                ->count();
            $qualified = Touchpoint::where('tracking_link_id', $link->id)
                ->where('event_milestone', 'conversation_qualified')
                ->count();
            $won = Touchpoint::where('tracking_link_id', $link->id)
                ->where('event_milestone', 'deal_won')
                ->count();

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
