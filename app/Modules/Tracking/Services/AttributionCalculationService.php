<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AttributionCalculationService
{
    public const MODELS = ['first_touch', 'last_touch', 'linear'];

    public function isWonDeal(Deal $deal): bool
    {
        $deal->loadMissing('pipelineStage');

        return $deal->pipelineStage?->category === 'won';
    }

    /**
     * Resolve chronological deduplicated touchpoints for a deal.
     *
     * @return Collection<int, Touchpoint>
     */
    public function resolveDealTouchpoints(Deal $deal): Collection
    {
        $convertedLeadId = Lead::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $deal->organization_id)
            ->where('converted_deal_id', $deal->id)
            ->value('id');

        $touchpoints = Touchpoint::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $deal->organization_id)
            ->whereNotNull('tracking_link_id')
            ->where(function ($query) use ($deal, $convertedLeadId): void {
                $query->where('deal_id', $deal->id);

                if ($convertedLeadId !== null) {
                    $query->orWhere('lead_id', (int) $convertedLeadId);
                }

                if ($deal->contact_id !== null) {
                    $query->orWhere(function ($contactQuery) use ($deal): void {
                        $contactQuery->where('contact_id', $deal->contact_id)
                            ->whereNull('deal_id');
                    });
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($touchpoints->isEmpty()) {
            return collect();
        }

        $hasPreDealTouchpoints = $touchpoints->contains(
            static fn (Touchpoint $tp): bool => $tp->event_milestone !== 'deal_won',
        );

        if ($hasPreDealTouchpoints) {
            $touchpoints = $touchpoints
                ->reject(static fn (Touchpoint $tp): bool => $tp->event_milestone === 'deal_won')
                ->values();
        }

        $seenClickIds = [];

        return $touchpoints
            ->filter(function (Touchpoint $tp) use (&$seenClickIds): bool {
                if ($tp->link_click_id === null) {
                    return true;
                }

                $clickId = (int) $tp->link_click_id;
                if (isset($seenClickIds[$clickId])) {
                    return false;
                }

                $seenClickIds[$clickId] = true;

                return true;
            })
            ->values();
    }

    /**
     * Attribute revenue for a single Won deal using exact-sum cent distribution.
     *
     * @return array<int, array{tracking_link_id: int, touchpoint_id: int, cents: int, amount: float}>
     */
    public function attributeDeal(Deal $deal, string $model = 'first_touch'): array
    {
        if (! in_array($model, self::MODELS, true)) {
            throw new InvalidArgumentException("Unsupported attribution model [{$model}].");
        }

        if (! $this->isWonDeal($deal)) {
            return [];
        }

        $totalCents = (int) round(((float) $deal->amount) * 100);
        if ($totalCents <= 0) {
            return [];
        }

        $touchpoints = $this->resolveDealTouchpoints($deal);
        if ($touchpoints->isEmpty()) {
            return [];
        }

        if ($model === 'first_touch') {
            /** @var Touchpoint $first */
            $first = $touchpoints->first();

            return [[
                'tracking_link_id' => (int) $first->tracking_link_id,
                'touchpoint_id' => (int) $first->id,
                'cents' => $totalCents,
                'amount' => round($totalCents / 100, 2),
            ]];
        }

        if ($model === 'last_touch') {
            /** @var Touchpoint $last */
            $last = $touchpoints->last();

            return [[
                'tracking_link_id' => (int) $last->tracking_link_id,
                'touchpoint_id' => (int) $last->id,
                'cents' => $totalCents,
                'amount' => round($totalCents / 100, 2),
            ]];
        }

        $count = $touchpoints->count();
        $baseCents = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;

        $allocations = [];
        foreach ($touchpoints->values() as $index => $tp) {
            $cents = $baseCents + ($index < $remainder ? 1 : 0);
            $allocations[] = [
                'tracking_link_id' => (int) $tp->tracking_link_id,
                'touchpoint_id' => (int) $tp->id,
                'cents' => $cents,
                'amount' => round($cents / 100, 2),
            ];
        }

        return $allocations;
    }

    /**
     * Aggregate attributed revenue, clicks, leads, won deals, and ROAS per tracking link.
     *
     * @return array<int, array{
     *     tracking_link_id: int,
     *     code: string,
     *     name: string,
     *     channel: string,
     *     ad_spend: float,
     *     clicks_count: int,
     *     leads_count: int,
     *     won_deals_count: int,
     *     attributed_cents: int,
     *     attributed_revenue: float,
     *     roas: float|null
     * }>
     */
    public function aggregateByTrackingLink(int $organizationId, string $model = 'first_touch'): array
    {
        if (! in_array($model, self::MODELS, true)) {
            throw new InvalidArgumentException("Unsupported attribution model [{$model}].");
        }

        $links = TrackingLink::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->withCount('clicks')
            ->orderBy('id')
            ->get();

        $leadCountsByLink = Touchpoint::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereNotNull('tracking_link_id')
            ->whereNotNull('lead_id')
            ->selectRaw('tracking_link_id, COUNT(DISTINCT lead_id) as leads_count')
            ->groupBy('tracking_link_id')
            ->pluck('leads_count', 'tracking_link_id');

        $centsByLink = [];
        $wonDealsByLink = [];

        $wonDeals = Deal::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->with('pipelineStage')
            ->whereHas('pipelineStage', static fn ($q) => $q->where('category', 'won'))
            ->get();

        foreach ($wonDeals as $deal) {
            $allocations = $this->attributeDeal($deal, $model);
            $dealLinkIds = [];

            foreach ($allocations as $allocation) {
                $linkId = $allocation['tracking_link_id'];
                $centsByLink[$linkId] = ($centsByLink[$linkId] ?? 0) + $allocation['cents'];
                $dealLinkIds[$linkId] = true;
            }

            foreach (array_keys($dealLinkIds) as $linkId) {
                $wonDealsByLink[$linkId] = ($wonDealsByLink[$linkId] ?? 0) + 1;
            }
        }

        return $links->map(function (TrackingLink $link) use ($centsByLink, $wonDealsByLink, $leadCountsByLink): array {
            $cents = (int) ($centsByLink[$link->id] ?? 0);
            $revenue = round($cents / 100, 2);
            $adSpend = round((float) $link->ad_spend, 2);

            return [
                'tracking_link_id' => (int) $link->id,
                'code' => $link->code,
                'name' => $link->name,
                'channel' => $link->channel,
                'ad_spend' => $adSpend,
                'clicks_count' => (int) $link->clicks_count,
                'leads_count' => (int) ($leadCountsByLink[$link->id] ?? 0),
                'won_deals_count' => (int) ($wonDealsByLink[$link->id] ?? 0),
                'attributed_cents' => $cents,
                'attributed_revenue' => $revenue,
                'roas' => $adSpend > 0 ? round($revenue / $adSpend, 2) : null,
            ];
        })->values()->all();
    }
}
