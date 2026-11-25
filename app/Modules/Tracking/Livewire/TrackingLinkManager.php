<?php

namespace App\Modules\Tracking\Livewire;

use App\Models\User;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class TrackingLinkManager extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $code = '';

    public string $slug = '';

    public string $destinationUrl = '';

    public string $channel = 'ad';

    public string $utmSource = '';

    public string $utmMedium = '';

    public string $utmCampaign = '';

    public string $adSpend = '0';

    public string $whatsappNumber = '';

    public string $whatsappMessage = '';

    /** @var array<int, int|string> */
    public array $selectedCustomFieldIds = [];

    public string $assignedUserId = '';

    public bool $autoAssignRoundRobin = false;

    public bool $showCreateModal = false;

    public ?int $editingLinkId = null;

    public string $editingLinkName = '';

    public string $editName = '';

    public string $editCode = '';

    public string $editSlug = '';

    public string $editChannel = 'ad';

    public string $editDestinationUrl = '';

    public string $editUtmSource = '';

    public string $editUtmMedium = '';

    public string $editUtmCampaign = '';

    public string $editAdSpend = '0';

    public string $editExpiresAt = '';

    public string $editWhatsappNumber = '';

    public string $editWhatsappMessage = '';

    /** @var array<int, int|string> */
    public array $editSelectedCustomFieldIds = [];

    public string $editAssignedUserId = '';

    public bool $editAutoAssignRoundRobin = false;

    public bool $showNewFieldForm = false;

    public string $newFieldTarget = 'create';

    public string $newFieldLabel = '';

    public string $newFieldKey = '';

    public string $newFieldDataType = 'text';

    public string $newFieldOptionsInput = '';

    public bool $newFieldIsRequired = false;

    public string $attributionModel = 'first_touch';

    public ?string $statusMessage = null;

    public function openCreateModal(): void
    {
        $this->authorize('create', TrackingLink::class);

        $this->resetValidation();
        $this->reset([
            'name',
            'code',
            'slug',
            'destinationUrl',
            'utmSource',
            'utmMedium',
            'utmCampaign',
            'whatsappNumber',
            'whatsappMessage',
            'selectedCustomFieldIds',
            'assignedUserId',
        ]);
        $this->channel = 'ad';
        $this->adSpend = '0';
        $this->autoAssignRoundRobin = false;
        $this->showNewFieldForm = false;
        $this->editingLinkId = null;
        $this->editingLinkName = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->showNewFieldForm = false;
        $this->resetValidation();
    }

    public function createLink(LinkShortenerService $shortener, TenantContext $tenantContext): void
    {
        $this->authorize('create', TrackingLink::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,code'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tracking_links,slug'],
            'destinationUrl' => ['nullable', 'url', 'max:2048'],
            'channel' => ['required', Rule::in(TrackingLink::CHANNELS)],
            'utmSource' => ['nullable', 'string', 'max:100'],
            'utmMedium' => ['nullable', 'string', 'max:100'],
            'utmCampaign' => ['nullable', 'string', 'max:100'],
            'adSpend' => ['nullable', 'numeric', 'min:0'],
            'whatsappNumber' => ['nullable', 'string', 'max:32'],
            'whatsappMessage' => ['nullable', 'string', 'max:1000'],
            'selectedCustomFieldIds' => ['nullable', 'array'],
            'selectedCustomFieldIds.*' => ['integer'],
            'assignedUserId' => ['nullable', 'integer'],
            'autoAssignRoundRobin' => ['boolean'],
        ]);

        $link = $shortener->create($tenantContext->getTenant(), [
            'name' => $validated['name'],
            'code' => $validated['code'] !== '' ? $validated['code'] : null,
            'slug' => $validated['slug'] !== '' ? $validated['slug'] : null,
            'destination_url' => $validated['destinationUrl'] !== '' ? $validated['destinationUrl'] : null,
            'channel' => $validated['channel'],
            'utm_source' => $validated['utmSource'] ?? null,
            'utm_medium' => $validated['utmMedium'] ?? null,
            'utm_campaign' => $validated['utmCampaign'] ?? null,
            'ad_spend' => $validated['adSpend'] ?? 0,
            'whatsapp_number' => $validated['whatsappNumber'] !== '' ? $validated['whatsappNumber'] : null,
            'whatsapp_message' => $validated['whatsappMessage'] !== '' ? $validated['whatsappMessage'] : null,
            'capture_custom_field_ids' => $validated['selectedCustomFieldIds'] ?? [],
            'assigned_user_id' => $validated['assignedUserId'] !== '' ? (int) $validated['assignedUserId'] : null,
            'auto_assign_round_robin' => (bool) ($validated['autoAssignRoundRobin'] ?? false),
        ]);

        $this->reset([
            'name',
            'code',
            'slug',
            'destinationUrl',
            'utmSource',
            'utmMedium',
            'utmCampaign',
            'whatsappNumber',
            'whatsappMessage',
            'selectedCustomFieldIds',
            'assignedUserId',
        ]);
        $this->channel = 'ad';
        $this->adSpend = '0';
        $this->autoAssignRoundRobin = false;
        $this->showNewFieldForm = false;
        $this->showCreateModal = false;
        $this->statusMessage = 'Tracking link created: '.$link->captureUrl();
    }

    public function startConfigure(int $linkId): void
    {
        $link = TrackingLink::query()->findOrFail($linkId);
        $this->authorize('update', $link);

        $this->resetValidation();
        $this->showCreateModal = false;
        $this->editingLinkId = (int) $link->id;
        $this->editingLinkName = (string) $link->name;
        $this->editName = (string) $link->name;
        $this->editCode = (string) $link->code;
        $this->editSlug = (string) ($link->slug ?? '');
        $this->editChannel = (string) $link->channel;
        $this->editDestinationUrl = (string) ($link->destination_url ?? '');
        $this->editUtmSource = (string) ($link->utm_source ?? '');
        $this->editUtmMedium = (string) ($link->utm_medium ?? '');
        $this->editUtmCampaign = (string) ($link->utm_campaign ?? '');
        $this->editAdSpend = $link->ad_spend !== null ? (string) $link->ad_spend : '0';
        $this->editExpiresAt = $link->expires_at !== null ? $link->expires_at->format('Y-m-d\TH:i') : '';
        $this->editWhatsappNumber = (string) ($link->whatsapp_number ?? '');
        $this->editWhatsappMessage = (string) ($link->whatsapp_message ?? '');
        $this->editSelectedCustomFieldIds = array_map('strval', $link->capture_custom_field_ids ?? []);
        $this->editAssignedUserId = $link->assigned_user_id ? (string) $link->assigned_user_id : '';
        $this->editAutoAssignRoundRobin = (bool) $link->auto_assign_round_robin;
        $this->showNewFieldForm = false;
    }

    public function saveCaptureConfig(LinkShortenerService $shortener): void
    {
        if ($this->editingLinkId === null) {
            return;
        }

        $link = TrackingLink::query()->findOrFail($this->editingLinkId);
        $this->authorize('update', $link);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:191'],
            'editCode' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('tracking_links', 'code')->ignore($link->id),
            ],
            'editSlug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('tracking_links', 'slug')->ignore($link->id),
            ],
            'editChannel' => ['required', Rule::in(TrackingLink::CHANNELS)],
            'editDestinationUrl' => ['nullable', 'url', 'max:2048'],
            'editUtmSource' => ['nullable', 'string', 'max:100'],
            'editUtmMedium' => ['nullable', 'string', 'max:100'],
            'editUtmCampaign' => ['nullable', 'string', 'max:100'],
            'editAdSpend' => ['nullable', 'numeric', 'min:0'],
            'editExpiresAt' => ['nullable', 'date'],
            'editWhatsappNumber' => ['nullable', 'string', 'max:32'],
            'editWhatsappMessage' => ['nullable', 'string', 'max:1000'],
            'editSelectedCustomFieldIds' => ['nullable', 'array'],
            'editSelectedCustomFieldIds.*' => ['integer'],
            'editAssignedUserId' => ['nullable', 'integer'],
            'editAutoAssignRoundRobin' => ['boolean'],
        ]);

        $code = Str::limit(Str::slug(trim((string) $validated['editCode'])), 32, '');
        if ($code === '') {
            $this->addError('editCode', 'Tracking link code must contain alphanumeric characters.');

            return;
        }
        if (TrackingLink::withoutGlobalScope(OrganizationScope::class)
            ->where('code', $code)
            ->where('id', '!=', $link->id)
            ->exists()) {
            $this->addError('editCode', 'The tracking link code has already been taken.');

            return;
        }

        $rawSlug = trim((string) ($validated['editSlug'] ?? ''));
        $slug = $rawSlug !== '' ? Str::limit(Str::slug($rawSlug), 100, '') : null;
        if ($slug !== null && $slug !== '' && TrackingLink::withoutGlobalScope(OrganizationScope::class)
            ->where('slug', $slug)
            ->where('id', '!=', $link->id)
            ->exists()) {
            $this->addError('editSlug', 'The tracking link slug has already been taken.');

            return;
        }

        $destinationUrl = trim((string) ($validated['editDestinationUrl'] ?? ''));
        if ($destinationUrl === '') {
            $destinationUrl = url('/go/'.($slug ?: $code));
        } else {
            $scheme = strtolower((string) parse_url($destinationUrl, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                $this->addError('editDestinationUrl', 'Destination URL must be a valid http or https URL.');

                return;
            }
        }

        $assignedUserId = $validated['editAssignedUserId'] !== '' && $validated['editAssignedUserId'] !== null
            ? (int) $validated['editAssignedUserId']
            : null;
        if ($assignedUserId !== null) {
            app(LeadAssignmentService::class)->assertOrganizationMember((int) $link->organization_id, $assignedUserId, 'editAssignedUserId');
        }

        $expiresAt = trim((string) ($validated['editExpiresAt'] ?? ''));

        $link->update([
            'name' => trim((string) $validated['editName']),
            'code' => $code,
            'slug' => $slug !== '' ? $slug : null,
            'channel' => (string) $validated['editChannel'],
            'destination_url' => $destinationUrl,
            'utm_source' => trim((string) ($validated['editUtmSource'] ?? '')) ?: null,
            'utm_medium' => trim((string) ($validated['editUtmMedium'] ?? '')) ?: null,
            'utm_campaign' => trim((string) ($validated['editUtmCampaign'] ?? '')) ?: null,
            'ad_spend' => $validated['editAdSpend'] !== null && $validated['editAdSpend'] !== '' ? $validated['editAdSpend'] : 0,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'whatsapp_number' => trim((string) ($validated['editWhatsappNumber'] ?? '')) ?: null,
            'whatsapp_message' => trim((string) ($validated['editWhatsappMessage'] ?? '')) ?: null,
            'capture_custom_field_ids' => $shortener->sanitizeCaptureCustomFieldIds(
                (int) $link->organization_id,
                $validated['editSelectedCustomFieldIds'] ?? [],
            ),
            'assigned_user_id' => $assignedUserId,
            'auto_assign_round_robin' => (bool) ($validated['editAutoAssignRoundRobin'] ?? false),
        ]);

        $this->editingLinkId = null;
        $this->editingLinkName = '';
        $this->showNewFieldForm = false;
        $this->statusMessage = 'Tracking link updated.';
    }

    public function cancelConfigure(): void
    {
        $this->editingLinkId = null;
        $this->editingLinkName = '';
        $this->showNewFieldForm = false;
        $this->resetValidation();
    }

    public function openNewFieldForm(string $target = 'create'): void
    {
        $this->authorize('create', CustomFieldDefinition::class);

        $this->resetValidation(['newFieldLabel', 'newFieldKey', 'newFieldDataType', 'newFieldOptionsInput']);
        $this->reset(['newFieldLabel', 'newFieldKey', 'newFieldOptionsInput', 'newFieldIsRequired']);
        $this->newFieldDataType = 'text';
        $this->newFieldTarget = $target === 'edit' ? 'edit' : 'create';
        $this->showNewFieldForm = true;
    }

    public function cancelNewFieldForm(): void
    {
        $this->showNewFieldForm = false;
        $this->resetValidation(['newFieldLabel', 'newFieldKey', 'newFieldDataType', 'newFieldOptionsInput']);
    }

    public function createInlineCustomField(TenantContext $tenantContext): void
    {
        $this->authorize('create', CustomFieldDefinition::class);

        $tenant = $tenantContext->getTenant();
        $label = trim($this->newFieldLabel);
        $rawKey = trim($this->newFieldKey);
        if ($rawKey === '' && $label !== '') {
            $rawKey = Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9\s_]/', '', $label) ?? ''));
            $rawKey = preg_replace('/_+/', '_', trim($rawKey, '_')) ?? '';
        }
        $this->newFieldKey = Str::lower($rawKey);

        $options = [];
        if (in_array($this->newFieldDataType, ['select', 'multiselect'], true)) {
            $options = array_values(array_filter(array_map(
                static fn (string $opt): string => trim($opt),
                explode(',', $this->newFieldOptionsInput),
            ), static fn (string $opt): bool => $opt !== ''));
        }

        $this->validate([
            'newFieldLabel' => ['required', 'string', 'max:100'],
            'newFieldKey' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'field_key')
                    ->where('organization_id', $tenant?->id)
                    ->where('entity_type', 'lead'),
            ],
            'newFieldDataType' => ['required', Rule::in(CustomFieldDefinition::DATA_TYPES)],
            'newFieldIsRequired' => ['boolean'],
        ]);

        if (in_array($this->newFieldDataType, ['select', 'multiselect'], true) && count($options) === 0) {
            $this->addError('newFieldOptionsInput', 'At least one comma-separated option is required for select fields.');

            return;
        }

        $nextOrder = (int) CustomFieldDefinition::query()
            ->where('entity_type', 'lead')
            ->max('order_index') + 1;

        $definition = CustomFieldDefinition::create([
            'organization_id' => $tenant->id,
            'entity_type' => 'lead',
            'field_key' => $this->newFieldKey,
            'label' => $label,
            'data_type' => $this->newFieldDataType,
            'options' => in_array($this->newFieldDataType, ['select', 'multiselect'], true) ? $options : null,
            'is_required' => $this->newFieldIsRequired,
            'order_index' => $nextOrder,
        ]);

        $idStr = (string) $definition->id;
        if ($this->newFieldTarget === 'edit' && $this->editingLinkId !== null) {
            if (! in_array($idStr, array_map('strval', $this->editSelectedCustomFieldIds), true)) {
                $this->editSelectedCustomFieldIds[] = $idStr;
            }
        } elseif (! in_array($idStr, array_map('strval', $this->selectedCustomFieldIds), true)) {
            $this->selectedCustomFieldIds[] = $idStr;
        }

        $this->reset(['newFieldLabel', 'newFieldKey', 'newFieldOptionsInput', 'newFieldIsRequired']);
        $this->newFieldDataType = 'text';
        $this->showNewFieldForm = false;
        $this->statusMessage = sprintf('Lead custom field "%s" created and selected.', $definition->label);
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

    public function render(
        AttributionCalculationService $attribution,
        TenantContext $tenantContext,
        CustomFieldRegistry $customFieldRegistry,
    ): View {
        $this->authorize('viewAny', TrackingLink::class);

        $tenant = $tenantContext->getTenant();
        $links = TrackingLink::query()
            ->with('assignedUser:id,name')
            ->withCount('clicks')
            ->orderByDesc('id')
            ->get();

        $summary = collect(
            $attribution->aggregateByTrackingLink((int) $tenant->id, $this->attributionModel)
        )->keyBy('tracking_link_id');

        $leadCustomFields = $customFieldRegistry->forEntity('lead', (int) $tenant->id);
        $leadCustomFieldsById = $leadCustomFields->keyBy('id');
        $orgSettings = OrganizationSetting::forOrganization((int) $tenant->id);
        $orgMemberIds = DB::table('organization_user')
            ->where('organization_id', (int) $tenant->id)
            ->pluck('user_id');
        $orgMembers = User::query()
            ->whereIn('id', $orgMemberIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.tracking.tracking-link-manager', [
            'links' => $links,
            'summary' => $summary,
            'channels' => TrackingLink::CHANNELS,
            'attributionModels' => AttributionCalculationService::MODELS,
            'leadCustomFields' => $leadCustomFields,
            'leadCustomFieldsById' => $leadCustomFieldsById,
            'customFieldDataTypes' => CustomFieldDefinition::DATA_TYPES,
            'orgSettings' => $orgSettings,
            'orgMembers' => $orgMembers,
        ]);
    }
}
