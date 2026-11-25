<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\DashboardMetricsService;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext,
        DashboardMetricsService $metricsService,
    ): View {
        $organization = $tenantContext->getTenant();
        $user = $request->user();
        $metrics = $metricsService->getMetrics($organization);

        return view('dashboard', [
            'organization' => $organization,
            'role' => $user->roleIn($organization) ?? ($user->isSuperadmin() ? 'superadmin' : null),
            'metrics' => $metrics,
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'members' => $organization->users()->orderBy('users.name')->get(['users.id', 'users.name']),
            'statuses' => Lead::PIPELINE_STATUSES,
        ]);
    }

    public function metricsJson(
        Request $request,
        TenantContext $tenantContext,
        DashboardMetricsService $metricsService,
    ): JsonResponse {
        $organization = $tenantContext->getTenant();
        $filters = $request->only([
            'date_from',
            'date_to',
            'campaign_id',
            'source',
            'assigned_user_id',
            'status',
        ]);

        return response()->json([
            'data' => $metricsService->getMetrics($organization, $filters),
        ]);
    }
}
