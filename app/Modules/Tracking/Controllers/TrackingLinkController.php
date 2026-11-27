<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tracking\Models\LinkClick;
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
            'campaigns' => Campaign::query()->orderByDesc('id')->get(['id', 'name']),
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

        $uniqueVisitors = LinkClick::query()
            ->where('organization_id', $tenant->id)
            ->whereNotNull('visitor_hash')
            ->selectRaw('tracking_link_id, COUNT(DISTINCT visitor_hash) as visitors_count')
            ->groupBy('tracking_link_id')
            ->pluck('visitors_count', 'tracking_link_id');

        $query = TrackingLink::query()
            ->with('campaign:id,name')
            ->withCount('clicks')
            ->orderByDesc('id');

        $channel = $request->query('channel');
        if (is_string($channel) && $channel !== '') {
            if (!in_array($channel, TrackingLink::CHANNELS, true)) {
                return response()->json(['message' => 'Invalid channel filter.'], 422);
            }
            $query->where('channel', $channel);
        }

        $links = $query->get()->map(function (TrackingLink $link) use ($leadCounts, $uniqueVisitors): array {
            return array_merge($link->toArray(), [
                'short_url' => $link->shortUrl(),
                'capture_url' => $link->captureUrl(),
                'clicks_count' => (int) $link->clicks_count,
                'unique_visitors' => (int) ($uniqueVisitors[$link->id] ?? 0),
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

        $tenantId = (int) $tenantContext->getTenantId();

        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('campaigns', $tenantId)],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,code'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,slug'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'channel' => ['required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'source' => ['nullable', 'string', 'max:100'],
            'medium' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'ad_spend' => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_message' => ['nullable', 'string', 'max:1000'],
            'capture_custom_field_ids' => ['nullable', 'array'],
            'capture_custom_field_ids.*' => ['integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'auto_assign_round_robin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $link = $shortener->create($tenantContext->getTenant(), $validated);

        return response()->json([
            'message' => 'Tracking link created.',
            'data' => array_merge($link->toArray(), [
                'short_url' => $link->shortUrl(),
                'capture_url' => $link->captureUrl(),
            ]),
        ], 201);
    }

    public function update(Request $request, TrackingLink $trackingLink, LinkShortenerService $shortener): JsonResponse
    {
        Gate::authorize('update', $trackingLink);

        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('campaigns', (int) $trackingLink->organization_id)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'code' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('tracking_links', 'code')->ignore($trackingLink->id)],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('tracking_links', 'slug')->ignore($trackingLink->id)],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'channel' => ['sometimes', 'required', 'string', Rule::in(TrackingLink::CHANNELS)],
            'source' => ['nullable', 'string', 'max:100'],
            'medium' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
            'ad_spend' => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_message' => ['nullable', 'string', 'max:1000'],
            'capture_custom_field_ids' => ['nullable', 'array'],
            'capture_custom_field_ids.*' => ['integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'auto_assign_round_robin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('destination_url', $validated)) {
            $destinationUrl = trim((string) ($validated['destination_url'] ?? ''));
            if ($destinationUrl === '') {
                $resolvedSlug = array_key_exists('slug', $validated) ? ($validated['slug'] ?: null) : $trackingLink->slug;
                $resolvedCode = $validated['code'] ?? $trackingLink->code;
                $validated['destination_url'] = url('/go/' . ($resolvedSlug ?: $resolvedCode));
            } else {
                $scheme = strtolower((string) parse_url($destinationUrl, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https'], true)) {
                    return response()->json([
                        'message' => 'Destination URL must use http or https.',
                        'errors' => ['destination_url' => ['Destination URL must use http or https.']],
                    ], 422);
                }
            }
        }

        if (array_key_exists('capture_custom_field_ids', $validated)) {
            $validated['capture_custom_field_ids'] = $shortener->sanitizeCaptureCustomFieldIds(
                (int) $trackingLink->organization_id,
                $validated['capture_custom_field_ids'],
            );
        }

        if (array_key_exists('assigned_user_id', $validated) && $validated['assigned_user_id'] !== null) {
            app(LeadAssignmentService::class)->assertOrganizationMember(
                (int) $trackingLink->organization_id,
                (int) $validated['assigned_user_id'],
            );
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
        if (!in_array($model, AttributionCalculationService::MODELS, true)) {
            return response()->json(['message' => 'Invalid attribution model.'], 422);
        }

        $tenant = $tenantContext->getTenant();

        return response()->json([
            'model' => $model,
            'data' => $attribution->aggregateByTrackingLink((int) $tenant->id, $model),
        ]);
    }
}
