<?php

namespace App\Modules\Analytics\Services;

use App\Models\Organization;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignMetricsService;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(
        private readonly CampaignMetricsService $campaignMetrics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMetrics(Organization $organization, array $filters = []): array
    {
        $tz = $organization->timezone ?: 'UTC';
        $nowTz = now($tz);
        $todayStartUtc = $nowTz->copy()->startOfDay()->utc();
        $todayEndUtc = $nowTz->copy()->endOfDay()->utc();
        $monthStartUtc = $nowTz->copy()->startOfMonth()->utc();

        $baseLeads = $this->applyLeadFilters(Lead::query(), $filters);

        $totalLeads = (clone $baseLeads)->count();
        $newLeads = (clone $baseLeads)->where('status', 'new')->count();
        $todaysLeads = (clone $baseLeads)->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->count();
        $monthlyLeads = (clone $baseLeads)->where('created_at', '>=', $monthStartUtc)->count();
        $unassignedLeads = (clone $baseLeads)->whereNull('assigned_user_id')->count();
        $qualifiedLeads = (clone $baseLeads)->whereIn('status', ['qualified', 'proposal_sent', 'negotiation'])->count();
        $wonLeads = (clone $baseLeads)->whereIn('status', ['won', 'converted'])->count();
        $lostLeads = (clone $baseLeads)->whereIn('status', ['lost', 'unqualified'])->count();

        $wonDealsFromPipeline = Deal::query()
            ->whereHas('pipelineStage', fn ($q) => $q->where('category', 'won'))
            ->count();
        $lostDealsFromPipeline = Deal::query()
            ->whereHas('pipelineStage', fn ($q) => $q->where('category', 'lost'))
            ->count();

        $wonDeals = max($wonLeads, $wonDealsFromPipeline);
        $lostDeals = max($lostLeads, $lostDealsFromPipeline);
        $conversionRate = $totalLeads > 0 ? round(($wonDeals / $totalLeads) * 100, 2) : 0.0;

        $followUpsDue = FollowUp::query()
            ->where('status', 'pending')
            ->where('due_at', '<=', $todayEndUtc)
            ->count();

        // Leads over time (last 14 days or filtered range)
        $leadsOverTime = (clone $baseLeads)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->orderBy('day')
            ->limit(30)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->day, 'value' => (int) $row->total])
            ->values()
            ->all();

        $leadsBySource = (clone $baseLeads)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => (string) ($row->source ?: 'direct'), 'value' => (int) $row->total])
            ->values()
            ->all();

        $leadsByEmployee = (clone $baseLeads)
            ->leftJoin('users', 'leads.assigned_user_id', '=', 'users.id')
            ->select(DB::raw("COALESCE(users.name, 'Unassigned') as employee_name"), DB::raw('COUNT(leads.id) as total'))
            ->groupBy('employee_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->employee_name, 'value' => (int) $row->total])
            ->values()
            ->all();

        $funnelStatuses = ['new', 'contacted', 'interested', 'qualified', 'proposal_sent', 'negotiation', 'won', 'lost'];
        $statusCounts = (clone $baseLeads)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $conversionFunnel = array_map(
            fn (string $st): array => [
                'label' => ucwords(str_replace('_', ' ', $st)),
                'status' => $st,
                'value' => (int) ($statusCounts[$st] ?? 0),
            ],
            $funnelStatuses,
        );

        $campaigns = Campaign::query()->orderByDesc('id')->limit(10)->get();
        $campaignPerformance = $this->campaignMetrics->attachSummaries($campaigns)
            ->map(fn (Campaign $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'status' => $c->status,
                'metrics' => $c->getAttribute('metrics'),
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                'total_leads' => $totalLeads,
                'new_leads' => $newLeads,
                'todays_leads' => $todaysLeads,
                'monthly_leads' => $monthlyLeads,
                'unassigned_leads' => $unassignedLeads,
                'follow_ups_due' => $followUpsDue,
                'qualified_leads' => $qualifiedLeads,
                'won_deals' => $wonDeals,
                'lost_deals' => $lostDeals,
                'conversion_rate' => $conversionRate,
            ],
            'charts' => [
                'leads_over_time' => $leadsOverTime,
                'leads_by_source' => $leadsBySource,
                'leads_by_employee' => $leadsByEmployee,
                'conversion_funnel' => $conversionFunnel,
                'won_vs_lost' => [
                    ['label' => 'Won', 'value' => $wonDeals],
                    ['label' => 'Lost', 'value' => $lostDeals],
                ],
            ],
            'campaign_performance' => $campaignPerformance,
            'follow_ups' => [
                'overdue' => FollowUp::query()->with(['lead:id,lead_reference,first_name,last_name', 'assignedUser:id,name'])->overdue()->orderBy('due_at')->limit(5)->get(),
                'today' => FollowUp::query()->with(['lead:id,lead_reference,first_name,last_name', 'assignedUser:id,name'])->today($tz)->orderBy('due_at')->limit(5)->get(),
                'upcoming' => FollowUp::query()->with(['lead:id,lead_reference,first_name,last_name', 'assignedUser:id,name'])->upcoming($tz)->orderBy('due_at')->limit(5)->get(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLeadFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('leads.created_at', '>=', Carbon::parse((string) $filters['date_from'])->toDateString());
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('leads.created_at', '<=', Carbon::parse((string) $filters['date_to'])->toDateString());
        }
        if (! empty($filters['campaign_id']) && ctype_digit((string) $filters['campaign_id'])) {
            $query->where('leads.campaign_id', (int) $filters['campaign_id']);
        }
        if (! empty($filters['source'])) {
            $query->where('leads.source', (string) $filters['source']);
        }
        if (! empty($filters['assigned_user_id']) && ctype_digit((string) $filters['assigned_user_id'])) {
            $query->where('leads.assigned_user_id', (int) $filters['assigned_user_id']);
        }
        if (! empty($filters['status']) && in_array((string) $filters['status'], Lead::STATUSES, true)) {
            $query->where('leads.status', (string) $filters['status']);
        }

        return $query;
    }
}
