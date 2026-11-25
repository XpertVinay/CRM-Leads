<?php

namespace App\Modules\Tracking\Livewire;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class TrackingLinkManager extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $code = '';

    public string $destinationUrl = '';

    public string $channel = 'ad';

    public string $utmSource = '';

    public string $utmMedium = '';

    public string $utmCampaign = '';

    public string $adSpend = '0';

    public string $attributionModel = 'first_touch';

    public ?string $statusMessage = null;

    public function createLink(LinkShortenerService $shortener, TenantContext $tenantContext): void
    {
        $this->authorize('create', TrackingLink::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,code'],
            'destinationUrl' => ['required', 'url', 'max:2048'],
            'channel' => ['required', Rule::in(TrackingLink::CHANNELS)],
            'utmSource' => ['nullable', 'string', 'max:100'],
            'utmMedium' => ['nullable', 'string', 'max:100'],
            'utmCampaign' => ['nullable', 'string', 'max:100'],
            'adSpend' => ['nullable', 'numeric', 'min:0'],
        ]);

        $shortener->create($tenantContext->getTenant(), [
            'name' => $validated['name'],
            'code' => $validated['code'] !== '' ? $validated['code'] : null,
            'destination_url' => $validated['destinationUrl'],
            'channel' => $validated['channel'],
            'utm_source' => $validated['utmSource'] ?? null,
            'utm_medium' => $validated['utmMedium'] ?? null,
            'utm_campaign' => $validated['utmCampaign'] ?? null,
            'ad_spend' => $validated['adSpend'] ?? 0,
        ]);

        $this->reset(['name', 'code', 'destinationUrl', 'utmSource', 'utmMedium', 'utmCampaign']);
        $this->channel = 'ad';
        $this->adSpend = '0';
        $this->statusMessage = 'Tracking link created.';
    }

    public function toggleActive(int $linkId): void
    {
        $link = TrackingLink::query()->findOrFail($linkId);
        $this->authorize('update', $link);

        $link->update(['is_active' => ! $link->is_active]);
        $this->statusMessage = $link->is_active ? 'Tracking link activated.' : 'Tracking link paused.';
    }

    public function deleteLink(int $linkId): void
    {
        $link = TrackingLink::query()->findOrFail($linkId);
        $this->authorize('delete', $link);

        $link->delete();
        $this->statusMessage = 'Tracking link deleted.';
    }

    public function render(AttributionCalculationService $attribution, TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', TrackingLink::class);

        $tenant = $tenantContext->getTenant();
        $links = TrackingLink::query()
            ->withCount('clicks')
            ->orderByDesc('id')
            ->get();

        $summary = collect(
            $attribution->aggregateByTrackingLink((int) $tenant->id, $this->attributionModel)
        )->keyBy('tracking_link_id');

        return view('livewire.tracking.tracking-link-manager', [
            'links' => $links,
            'summary' => $summary,
            'channels' => TrackingLink::CHANNELS,
            'attributionModels' => AttributionCalculationService::MODELS,
        ]);
    }
}
