<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrackingLinkController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TrackingLink::class);

        return view('tracking.index', [
            'channels' => TrackingLink::CHANNELS,
            'attributionModels' => AttributionCalculationService::MODELS,
        ]);
    }

    public function list(Request $request, TenantContext $tenantContext): JsonResponse
    {
        Gate::authorize('viewAny', TrackingLink::class);

        $tenant = $tenantContext->getTenant();

        $leadCounts = Touchpoint::query()
            ->where('organization_id', $tenant->id)
            ->whereNotNull('tracking_link_id')
            ->whereNotNull('lead_id')
            ->selectRaw('tracking_link_id, COUNT(DISTINCT lead_id) as leads_count')
            ->groupBy('tracking_link_id')
            ->pluck('leads_count', 'tracking_link_id');

        $query = TrackingLink::query()
            ->withCount('clicks')
            ->orderByDesc('id');

        $channel = $request->query('channel');
        if (is_string($channel) && $channel !== '') {
            if (! in_array($channel, TrackingLink::CHANNELS, true)) {
                return response()->json(['message' => 'Invalid channel filter.'], 422);
            }
            $query->where('channel', $channel);
        }

        $links = $query->get()->map(function (TrackingLink $link) use ($leadCounts): array {
            return array_merge($link->toArray(), [
                'short_url' => $link->shortUrl(),
                'clicks_count' => (int) $link->clicks_count,
                'leads_count' => (int) ($leadCounts[$link->id] ?? 0),
            ]);
        });

        return response()->json([
            'data' => $links,
        ]);
    }

    public function store(
        Request $request,
        LinkShortenerService $shortener,
        TenantContext $tenantContext,
    ): JsonResponse {
        Gate::authorize('create', TrackingLink::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,code'],
            'destination_url' => ['required', 'url', 'max:2048'],
            'channel' => ['required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'ad_spend' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $link = $shortener->create($tenantContext->getTenant(), $validated);

        return response()->json([
            'message' => 'Tracking link created.',
            'data' => array_merge($link->toArray(), [
                'short_url' => $link->shortUrl(),
            ]),
        ], 201);
    }

    public function update(Request $request, TrackingLink $trackingLink): JsonResponse
    {
        Gate::authorize('update', $trackingLink);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'destination_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'channel' => ['sometimes', 'required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'ad_spend' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['destination_url'])) {
            $scheme = strtolower((string) parse_url($validated['destination_url'], PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return response()->json([
                    'message' => 'Destination URL must use http or https.',
                    'errors' => ['destination_url' => ['Destination URL must use http or https.']],
                ], 422);
            }
        }

        $trackingLink->update($validated);

        return response()->json([
            'message' => 'Tracking link updated.',
            'data' => array_merge($trackingLink->fresh()->toArray(), [
                'short_url' => $trackingLink->shortUrl(),
            ]),
        ]);
    }

    public function destroy(TrackingLink $trackingLink): JsonResponse
    {
        Gate::authorize('delete', $trackingLink);

        $trackingLink->delete();

        return response()->json([
            'message' => 'Tracking link deleted.',
        ]);
    }

    public function attributionSummary(
        Request $request,
        AttributionCalculationService $attribution,
        TenantContext $tenantContext,
    ): JsonResponse {
        Gate::authorize('viewAny', TrackingLink::class);

        $model = (string) $request->query('model', 'first_touch');
        if (! in_array($model, AttributionCalculationService::MODELS, true)) {
            return response()->json(['message' => 'Invalid attribution model.'], 422);
        }

        $tenant = $tenantContext->getTenant();

        return response()->json([
            'model' => $model,
            'data' => $attribution->aggregateByTrackingLink((int) $tenant->id, $model),
        ]);
    }
}
