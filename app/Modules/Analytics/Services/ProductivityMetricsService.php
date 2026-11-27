<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Context\TenantContext;

class ProductivityMetricsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function perRepMetrics(): array
    {
        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            return [];
        }

        $members = $tenant->users()->get(['users.id', 'users.name', 'users.email']);

        $leadCounts = Lead::query()
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) as leads_count')
            ->pluck('leads_count', 'assigned_user_id');

        $dealAggs = Deal::query()
            ->leftJoin('pipeline_stages', 'deals.pipeline_stage_id', '=', 'pipeline_stages.id')
            ->whereNotNull('deals.assigned_user_id')
            ->groupBy('deals.assigned_user_id')
            ->selectRaw("
                deals.assigned_user_id,
                SUM(CASE WHEN COALESCE(pipeline_stages.category, 'open') = 'open' THEN 1 ELSE 0 END) as open_deals_count,
                SUM(CASE WHEN pipeline_stages.category = 'won' THEN 1 ELSE 0 END) as won_deals_count,
                SUM(CASE WHEN pipeline_stages.category = 'won' THEN deals.amount ELSE 0 END) as won_revenue
            ")
            ->get()
            ->keyBy('assigned_user_id');

        $rows = [];
        foreach ($members as $user) {
            $dealRow = $dealAggs->get($user->id);

            $rows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'leads_count' => (int) ($leadCounts->get($user->id) ?? 0),
                'open_deals_count' => (int) ($dealRow?->open_deals_count ?? 0),
                'won_deals_count' => (int) ($dealRow?->won_deals_count ?? 0),
                'won_revenue' => round((float) ($dealRow?->won_revenue ?? 0), 2),
            ];
        }

        return $rows;
    }
}
