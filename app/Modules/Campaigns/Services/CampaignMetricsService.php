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

        $clicksCount = 0;
        $uniqueVisitors = 0;
        if ($linkIds->isNotEmpty()) {
            $clickAgg = LinkClick::query()
                ->whereIn('tracking_link_id', $linkIds)
                ->selectRaw('COUNT(*) as clicks_count, COUNT(DISTINCT visitor_hash) as unique_visitors')
                ->first();
            $clicksCount = (int) ($clickAgg?->clicks_count ?? 0);
            $uniqueVisitors = (int) ($clickAgg?->unique_visitors ?? 0);
        }

        $leadAgg = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) as leads_count,
                SUM(CASE WHEN status IN ('qualified', 'proposal_sent', 'negotiation', 'won', 'converted') THEN 1 ELSE 0 END) as qualified_count,
                SUM(CASE WHEN status IN ('won', 'converted') THEN 1 ELSE 0 END) as won_leads_count
            ")
            ->first();

        $leadsCount = (int) ($leadAgg?->leads_count ?? 0);
        $qualifiedCount = (int) ($leadAgg?->qualified_count ?? 0);
        $wonLeadsCount = (int) ($leadAgg?->won_leads_count ?? 0);

        $convertedDealIds = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('converted_deal_id')
            ->pluck('converted_deal_id');

        $dealAgg = $convertedDealIds->isNotEmpty()
            ? Deal::query()
                ->whereIn('id', $convertedDealIds)
                ->whereHas('pipelineStage', fn($q) => $q->where('category', 'won'))
                ->selectRaw('COUNT(*) as won_count, COALESCE(SUM(amount), 0) as revenue')
                ->first()
            : null;

        $totalWonDeals = max($wonLeadsCount, (int) ($dealAgg?->won_count ?? 0));
        $revenue = (float) ($dealAgg?->revenue ?? 0);
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
        if ($campaigns->isEmpty()) {
            return $campaigns;
        }

        $campaignIds = $campaigns->pluck('id')->all();

        $linksByCampaign = \App\Modules\Tracking\Models\TrackingLink::query()
            ->whereIn('campaign_id', $campaignIds)
            ->get(['id', 'campaign_id'])
            ->groupBy('campaign_id');

        $allLinkIds = $linksByCampaign->flatten()->pluck('id')->all();
        $clickAggsByLink = $allLinkIds !== []
            ? LinkClick::query()
                ->whereIn('tracking_link_id', $allLinkIds)
                ->groupBy('tracking_link_id')
                ->selectRaw('tracking_link_id, COUNT(*) as clicks_count, COUNT(DISTINCT visitor_hash) as unique_visitors')
                ->get()
                ->keyBy('tracking_link_id')
            : collect();

        $leadAggsByCampaign = Lead::query()
            ->whereIn('campaign_id', $campaignIds)
            ->groupBy('campaign_id')
            ->selectRaw("
                campaign_id,
                COUNT(*) as leads_count,
                SUM(CASE WHEN status IN ('qualified', 'proposal_sent', 'negotiation', 'won', 'converted') THEN 1 ELSE 0 END) as qualified_count,
                SUM(CASE WHEN status IN ('won', 'converted') THEN 1 ELSE 0 END) as won_leads_count
            ")
            ->get()
            ->keyBy('campaign_id');

        $dealLinks = Lead::query()
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull('converted_deal_id')
            ->get(['campaign_id', 'converted_deal_id'])
            ->groupBy('campaign_id');

        $allDealIds = $dealLinks->flatten()->pluck('converted_deal_id')->unique()->values()->all();
        $wonDealsById = $allDealIds !== []
            ? Deal::query()
                ->whereIn('id', $allDealIds)
                ->whereHas('pipelineStage', fn($q) => $q->where('category', 'won'))
                ->get(['id', 'amount'])
                ->keyBy('id')
            : collect();

        return $campaigns->map(function (Campaign $campaign) use ($linksByCampaign, $clickAggsByLink, $leadAggsByCampaign, $dealLinks, $wonDealsById): Campaign {
            $links = $linksByCampaign->get($campaign->id, collect());
            $clicksCount = 0;
            $uniqueVisitors = 0;
            foreach ($links as $link) {
                $agg = $clickAggsByLink->get($link->id);
                $clicksCount += (int) ($agg?->clicks_count ?? 0);
                $uniqueVisitors += (int) ($agg?->unique_visitors ?? 0);
            }

            $leadAgg = $leadAggsByCampaign->get($campaign->id);
            $leadsCount = (int) ($leadAgg?->leads_count ?? 0);
            $qualifiedCount = (int) ($leadAgg?->qualified_count ?? 0);
            $wonLeadsCount = (int) ($leadAgg?->won_leads_count ?? 0);

            $campaignWonDeals = $dealLinks->get($campaign->id, collect())
                ->map(fn($row) => $wonDealsById->get($row->converted_deal_id))
                ->filter();

            $totalWonDeals = max($wonLeadsCount, $campaignWonDeals->count());
            $revenue = (float) $campaignWonDeals->sum('amount');
            $conversionRate = $leadsCount > 0 ? round(($totalWonDeals / $leadsCount) * 100, 2) : 0.0;

            $campaign->setAttribute('metrics', [
                'links_count' => $links->count(),
                'clicks' => $clicksCount,
                'unique_visitors' => $uniqueVisitors,
                'leads' => $leadsCount,
                'qualified_leads' => $qualifiedCount,
                'won_deals' => $totalWonDeals,
                'conversion_rate' => $conversionRate,
                'revenue' => round($revenue, 2),
            ]);

            return $campaign;
        });
    }
}
