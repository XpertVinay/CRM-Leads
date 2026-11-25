<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tracking\Models\LinkClick;
use Illuminate\Support\Collection;

class CampaignMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(Campaign $campaign): array
    {
        $linkIds = $campaign->trackingLinks()->pluck('id');

        $clicksCount = $linkIds->isNotEmpty()
            ? LinkClick::query()->whereIn('tracking_link_id', $linkIds)->count()
            : 0;

        $uniqueVisitors = $linkIds->isNotEmpty()
            ? LinkClick::query()->whereIn('tracking_link_id', $linkIds)
                ->whereNotNull('visitor_hash')
                ->distinct('visitor_hash')
                ->count('visitor_hash')
            : 0;

        $leadsQuery = Lead::query()->where('campaign_id', $campaign->id);
        $leadsCount = (clone $leadsQuery)->count();
        $qualifiedCount = (clone $leadsQuery)->whereIn('status', ['qualified', 'proposal_sent', 'negotiation', 'won', 'converted'])->count();
        $wonLeadsCount = (clone $leadsQuery)->whereIn('status', ['won', 'converted'])->count();

        $convertedDealIds = (clone $leadsQuery)
            ->whereNotNull('converted_deal_id')
            ->pluck('converted_deal_id');

        $wonDeals = $convertedDealIds->isNotEmpty()
            ? Deal::query()
                ->whereIn('id', $convertedDealIds)
                ->whereHas('pipelineStage', fn ($q) => $q->where('category', 'won'))
                ->get(['id', 'amount'])
            : collect();

        $totalWonDeals = max($wonLeadsCount, $wonDeals->count());
        $revenue = (float) $wonDeals->sum('amount');
        $conversionRate = $leadsCount > 0 ? round(($totalWonDeals / $leadsCount) * 100, 2) : 0.0;

        return [
            'links_count' => $linkIds->count(),
            'clicks' => $clicksCount,
            'unique_visitors' => $uniqueVisitors,
            'leads' => $leadsCount,
            'qualified_leads' => $qualifiedCount,
            'won_deals' => $totalWonDeals,
            'conversion_rate' => $conversionRate,
            'revenue' => round($revenue, 2),
        ];
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return Collection<int, Campaign>
     */
    public function attachSummaries(Collection $campaigns): Collection
    {
        return $campaigns->map(function (Campaign $campaign): Campaign {
            $campaign->setAttribute('metrics', $this->summarize($campaign));

            return $campaign;
        });
    }
}
