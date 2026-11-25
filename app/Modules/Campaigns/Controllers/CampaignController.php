<?php

namespace App\Modules\Campaigns\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignMetricsService;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Campaign::class);

        return view('campaigns.index', [
            'types' => Campaign::TYPES,
            'platforms' => Campaign::PLATFORMS,
            'statuses' => Campaign::STATUSES,
        ]);
    }

    public function show(Campaign $campaign, CampaignMetricsService $metrics): View
    {
        Gate::authorize('view', $campaign);

        $campaign->load(['creator:id,name', 'trackingLinks']);

        return view('campaigns.show', [
            'campaign' => $campaign,
            'metrics' => $metrics->summarize($campaign),
        ]);
    }

    public function list(Request $request, CampaignMetricsService $metrics): JsonResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        $query = Campaign::query()->with('creator:id,name')->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, Campaign::STATUSES, true)) {
            $query->where('status', $status);
        }

        $platform = $request->query('platform');
        if (is_string($platform) && in_array($platform, Campaign::PLATFORMS, true)) {
            $query->where('platform', $platform);
        }

        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $needle = '%'.Str::lower(trim($search)).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$needle]);
        }

        $campaigns = $metrics->attachSummaries($query->get());

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request, TenantContext $tenantContext, CampaignMetricsService $metrics): JsonResponse
    {
        Gate::authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(Campaign::TYPES)],
            'platform' => ['required', 'string', Rule::in(Campaign::PLATFORMS)],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'string', Rule::in(Campaign::STATUSES)],
        ]);

        $campaign = Campaign::create([
            'organization_id' => $tenantContext->getTenantId(),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'platform' => $validated['platform'],
            'budget' => $validated['budget'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'created_by' => $request->user()?->id,
        ]);

        $campaign->setAttribute('metrics', $metrics->summarize($campaign));

        return response()->json([
            'message' => 'Campaign created successfully.',
            'data' => $campaign,
        ], 201);
    }

    public function update(Request $request, Campaign $campaign, CampaignMetricsService $metrics): JsonResponse
    {
        Gate::authorize('update', $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'required', 'string', Rule::in(Campaign::TYPES)],
            'platform' => ['sometimes', 'required', 'string', Rule::in(Campaign::PLATFORMS)],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Campaign::STATUSES)],
        ]);

        $campaign->update($validated);
        $campaign->setAttribute('metrics', $metrics->summarize($campaign->fresh()));

        return response()->json([
            'message' => 'Campaign updated successfully.',
            'data' => $campaign,
        ]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        Gate::authorize('delete', $campaign);

        $campaign->delete();

        return response()->json(['message' => 'Campaign archived successfully.']);
    }
}
