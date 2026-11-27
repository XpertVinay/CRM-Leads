<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\DashboardMetricsService;
use App\Modules\Analytics\Services\ProductivityMetricsService;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignMetricsService;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('reports.view');

        $tenant = $tenantContext->getTenant();

        return view('reports.index', [
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'members' => $tenant->users()->get(['users.id', 'users.name'])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ]);
    }

    public function data(
        Request $request,
        TenantContext $tenantContext,
        DashboardMetricsService $dashboardMetrics,
        CampaignMetricsService $campaignMetrics,
        ProductivityMetricsService $productivity,
    ): JsonResponse {
        Gate::authorize('reports.view');

        $tenant = $tenantContext->getTenant();
        $type = (string) $request->query('type', 'acquisition');
        $filters = $request->only(['date_from', 'date_to', 'campaign_id', 'source', 'assigned_user_id', 'status']);

        $payload = match ($type) {
            'campaigns' => $campaignMetrics->attachSummaries(Campaign::query()->orderByDesc('id')->get())->map(fn(Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'status' => $c->status,
                'budget' => $c->budget,
                'metrics' => $c->getAttribute('metrics'),
            ])->values()->all(),
            'employees' => $productivity->perRepMetrics(),
            'follow_ups' => [
                'overdue' => FollowUp::query()->overdue()->count(),
                'today' => FollowUp::query()->today($tenant->timezone ?? 'UTC')->count(),
                'upcoming' => FollowUp::query()->upcoming($tenant->timezone ?? 'UTC')->count(),
                'completed' => FollowUp::query()->where('status', 'completed')->count(),
            ],
            default => $dashboardMetrics->getMetrics($tenant, $filters),
        };

        return response()->json([
            'type' => $type,
            'data' => $payload,
        ]);
    }

    public function exportCsv(
        Request $request,
        TenantContext $tenantContext,
        CampaignMetricsService $campaignMetrics,
        ProductivityMetricsService $productivity,
    ): StreamedResponse {
        Gate::authorize('reports.view');

        $requestedType = (string) $request->query('type', 'campaigns');
        $type = in_array($requestedType, ['campaigns', 'employees'], true) ? $requestedType : 'campaigns';
        $filename = 'crm-report-' . $type . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($type, $campaignMetrics, $productivity): void {
            $handle = fopen('php://output', 'w');

            if ($type === 'employees') {
                fputcsv($handle, ['Employee', 'Assigned Leads', 'Won Deals', 'Revenue', 'Activities']);
                foreach ($productivity->perRepMetrics() as $rep) {
                    fputcsv($handle, \App\Support\Security\CsvSanitizer::sanitizeRow([
                        $rep['user_name'] ?? $rep['name'] ?? 'Unknown',
                        $rep['leads_count'] ?? 0,
                        $rep['won_deals_count'] ?? $rep['won_deals'] ?? 0,
                        $rep['won_revenue'] ?? 0,
                        $rep['activities_count'] ?? 0,
                    ]));
                }
            } else {
                fputcsv($handle, ['Campaign', 'Platform', 'Status', 'Clicks', 'Unique Visitors', 'Leads', 'Qualified', 'Won Deals', 'Conversion %', 'Revenue']);
                $campaigns = $campaignMetrics->attachSummaries(Campaign::query()->orderByDesc('id')->get());
                foreach ($campaigns as $c) {
                    $m = $c->getAttribute('metrics');
                    fputcsv($handle, \App\Support\Security\CsvSanitizer::sanitizeRow([
                        $c->name,
                        $c->platform,
                        $c->status,
                        $m['clicks'] ?? 0,
                        $m['unique_visitors'] ?? 0,
                        $m['leads'] ?? 0,
                        $m['qualified_leads'] ?? 0,
                        $m['won_deals'] ?? 0,
                        $m['conversion_rate'] ?? 0,
                        $m['revenue'] ?? 0,
                    ]));
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
