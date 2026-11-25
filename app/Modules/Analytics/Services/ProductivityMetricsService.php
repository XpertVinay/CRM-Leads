<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Context\TenantContext;

class ProductivityMetricsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function perRepMetrics(): array
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            return [];
        }

        $members = $tenant->users()->get();
        $leads = Lead::all();
        $deals = Deal::with('pipelineStage')->get();

        $rows = [];
        foreach ($members as $user) {
            $repLeads = $leads->where('assigned_user_id', $user->id);
            $repDeals = $deals->where('assigned_user_id', $user->id);

            $openDeals = $repDeals->filter(fn (Deal $d) => ($d->pipelineStage?->category ?? 'open') === 'open');
            $wonDeals = $repDeals->filter(fn (Deal $d) => $d->pipelineStage?->category === 'won');

            $rows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'leads_count' => $repLeads->count(),
                'open_deals_count' => $openDeals->count(),
                'won_deals_count' => $wonDeals->count(),
                'won_revenue' => round((float) $wonDeals->sum('amount'), 2),
            ];
        }

        return $rows;
    }
}
