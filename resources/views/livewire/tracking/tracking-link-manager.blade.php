<div>
    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Campaign Tracking Links &amp; Capture Forms</h2>
            <div class="crm-flex" style="gap:0.75rem;flex-wrap:wrap;">
                <div class="crm-flex">
                    <label class="crm-label" style="margin:0;">Attribution Model:</label>
                    <select class="crm-select" wire:model.live="attributionModel" style="width:auto;">
                        @foreach($attributionModels as $model)
                            <option value="{{ $model }}">{{ ucwords(str_replace('_', ' ', $model)) }}</option>
                        @endforeach
                    </select>
                </div>
                @can('create', \App\Modules\Tracking\Models\TrackingLink::class)
                    <button type="button" wire:click="openCreateModal" class="crm-btn crm-btn-primary crm-btn-sm">
                        + Create Tracking Link
                    </button>
                @endcan
            </div>
        </div>

        @if($statusMessage)
            <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
        @endif

        @if(! $orgSettings->whatsapp_phone)
            <div class="crm-alert crm-alert-warning crm-mb-2" style="background:var(--crm-warning-container, #fff8e1);color:var(--crm-on-warning-container, #7a4f01);border:1px solid #f0c36d;padding:0.65rem 0.9rem;border-radius:var(--crm-radius-sm);">
                Organization default WhatsApp number is not set. Set a WhatsApp number on each tracking link (via <strong>Edit</strong>) or in <a href="{{ route('settings.organization') }}">Organization Settings</a> so <code>/go/{slug}</code> submissions can redirect to WhatsApp.
            </div>
        @endif

        @if($links->isEmpty())
            <div style="padding:1.5rem;border:1px dashed var(--crm-border);border-radius:var(--crm-radius-md);text-align:center;" class="crm-muted">
                No tracking links created yet.
            </div>
        @else
            <div class="crm-grid" style="grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));gap:1rem;">
                @foreach($links as $link)
                    @php
                        $metrics = $summary->get($link->id, []);
                        $attachedIds = is_array($link->capture_custom_field_ids) ? $link->capture_custom_field_ids : [];
                    @endphp
                    <div wire:key="tracking-link-{{ $link->id }}" style="display:flex;flex-direction:column;justify-content:space-between;gap:0.85rem;padding:1.1rem;border:1px solid var(--crm-ghost-border);border-radius:var(--crm-radius-md);background:var(--crm-surface-low);">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.75rem;margin-bottom:0.65rem;">
                                <div style="min-width:0;">
                                    <div style="font-weight:700;font-size:1rem;color:var(--crm-text);word-break:break-word;">{{ $link->name }}</div>
                                    <div class="crm-muted" style="font-size:12px;word-break:break-all;margin-top:0.15rem;">{{ $link->destination_url }}</div>
                                </div>
                                <div style="display:flex;align-items:center;gap:0.35rem;flex-shrink:0;">
                                    <span class="crm-badge">{{ $link->channel }}</span>
                                    <span class="crm-badge {{ $link->is_active ? 'crm-badge-active' : 'crm-badge-suspended' }}">
                                        {{ $link->is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </div>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:0.4rem;padding:0.65rem 0.75rem;border:1px solid var(--crm-ghost-border);border-radius:var(--crm-radius-sm);background:var(--crm-surface);margin-bottom:0.75rem;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <div style="min-width:0;font-size:12px;">
                                        <span class="crm-muted">Short:</span>
                                        <a href="{{ $link->shortUrl() }}" target="_blank" rel="noopener" style="word-break:break-all;"><code>{{ $link->shortUrl() }}</code></a>
                                    </div>
                                    <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" style="padding:0.15rem 0.45rem;font-size:11px;" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $link->shortUrl() }}')">Copy</button>
                                </div>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <div style="min-width:0;font-size:12px;">
                                        <span class="crm-muted">Capture:</span>
                                        <a href="{{ $link->captureUrl() }}" target="_blank" rel="noopener" style="word-break:break-all;"><code>{{ $link->captureUrl() }}</code></a>
                                    </div>
                                    <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" style="padding:0.15rem 0.45rem;font-size:11px;" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $link->captureUrl() }}')">Copy</button>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(4, minmax(0, 1fr));gap:0.5rem;margin-bottom:0.75rem;">
                                <div style="padding:0.45rem 0.55rem;border-radius:var(--crm-radius-sm);background:var(--crm-surface);border:1px solid var(--crm-ghost-border);">
                                    <div class="crm-muted" style="font-size:11px;">Clicks</div>
                                    <div style="font-weight:700;font-size:0.95rem;">{{ $link->clicks_count }}</div>
                                </div>
                                <div style="padding:0.45rem 0.55rem;border-radius:var(--crm-radius-sm);background:var(--crm-surface);border:1px solid var(--crm-ghost-border);">
                                    <div class="crm-muted" style="font-size:11px;">Leads</div>
                                    <div style="font-weight:700;font-size:0.95rem;">{{ $metrics['leads_count'] ?? 0 }}</div>
                                </div>
                                <div style="padding:0.45rem 0.55rem;border-radius:var(--crm-radius-sm);background:var(--crm-surface);border:1px solid var(--crm-ghost-border);">
                                    <div class="crm-muted" style="font-size:11px;">Won Deals</div>
                                    <div style="font-weight:700;font-size:0.95rem;">{{ $metrics['won_deals_count'] ?? 0 }}</div>
                                </div>
                                <div style="padding:0.45rem 0.55rem;border-radius:var(--crm-radius-sm);background:var(--crm-surface);border:1px solid var(--crm-ghost-border);">
                                    <div class="crm-muted" style="font-size:11px;">Revenue</div>
                                    <div style="font-weight:700;font-size:0.95rem;">${{ number_format((float) ($metrics['attributed_revenue'] ?? 0), 2) }}</div>
                                </div>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:0.35rem;font-size:12px;">
                                <div>
                                    <span class="crm-muted">Owner:</span>
                                    <span>{{ $link->assignedUser?->name ?? ($link->auto_assign_round_robin ? 'Round-Robin' : 'Unassigned') }}</span>
                                    @if($link->utm_source || $link->utm_medium || $link->utm_campaign)
                                        <span class="crm-muted" style="margin-left:0.5rem;">• UTM:</span>
                                        <span>{{ collect([$link->utm_source, $link->utm_medium, $link->utm_campaign])->filter()->implode(' / ') }}</span>
                                    @endif
                                </div>
                                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0.3rem;">
                                    <span class="crm-muted">Capture Fields:</span>
                                    @forelse($attachedIds as $fid)
                                        @if($leadCustomFieldsById->has((int) $fid))
                                            @php($cf = $leadCustomFieldsById->get((int) $fid))
                                            <span class="crm-badge">{{ $cf->label }}@if($cf->is_required) *@endif</span>
                                        @endif
                                    @empty
                                        <span class="crm-muted">Standard fields only</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="crm-flex" style="justify-content:flex-end;gap:0.45rem;padding-top:0.65rem;border-top:1px solid var(--crm-ghost-border);">
                            @can('update', $link)
                                <button type="button" wire:click="startConfigure({{ $link->id }})" class="crm-btn crm-btn-secondary crm-btn-sm">
                                    Edit
                                </button>
                                <button type="button" wire:click="toggleActive({{ $link->id }})" class="crm-btn crm-btn-secondary crm-btn-sm">
                                    {{ $link->is_active ? 'Pause' : 'Activate' }}
                                </button>
                            @endcan
                            @can('delete', $link)
                                <button type="button" wire:click="deleteLink({{ $link->id }})" class="crm-btn crm-btn-danger crm-btn-sm">
                                    Delete
                                </button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($editingLinkId !== null)
        <div class="crm-modal-backdrop open">
            <div class="crm-modal" style="max-width:860px;width:95%;max-height:90vh;overflow-y:auto;">
                <div class="crm-card-header">
                    <h3 class="crm-card-title">Edit Tracking Link — {{ $editingLinkName }}</h3>
                    <button type="button" wire:click="cancelConfigure" class="crm-btn crm-btn-secondary crm-btn-sm">Close</button>
                </div>

                @if($statusMessage)
                    <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
                @endif

                <div class="crm-grid crm-grid-3 crm-mb-1">
                    <div class="crm-form-group">
                        <label class="crm-label">Campaign / Link Name *</label>
                        <input type="text" class="crm-input" wire:model="editName" placeholder="Spring Launch Ads">
                        @error('editName') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Short Code *</label>
                        <input type="text" class="crm-input" wire:model="editCode" placeholder="spring-ads">
                        @error('editCode') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Capture URL Slug (/go/{slug})</label>
                        <input type="text" class="crm-input" wire:model="editSlug" placeholder="leave blank to use short code">
                        @error('editSlug') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Channel</label>
                        <select class="crm-select" wire:model="editChannel">
                            @foreach($channels as $ch)
                                <option value="{{ $ch }}">{{ ucfirst($ch) }}</option>
                            @endforeach
                        </select>
                        @error('editChannel') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Destination URL (optional)</label>
                        <input type="url" class="crm-input" wire:model="editDestinationUrl" placeholder="Leave blank to open /go/{slug} capture form">
                        @error('editDestinationUrl') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Ad Spend</label>
                        <input type="number" step="0.01" min="0" class="crm-input" wire:model="editAdSpend">
                        @error('editAdSpend') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">UTM Source</label>
                        <input type="text" class="crm-input" wire:model="editUtmSource" placeholder="meta">
                        @error('editUtmSource') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">UTM Medium</label>
                        <input type="text" class="crm-input" wire:model="editUtmMedium" placeholder="cpc">
                        @error('editUtmMedium') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">UTM Campaign</label>
                        <input type="text" class="crm-input" wire:model="editUtmCampaign" placeholder="spring_launch">
                        @error('editUtmCampaign') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">
                            WhatsApp Destination Number
                            @if($orgSettings->whatsapp_phone)
                                <span class="crm-muted" style="font-weight:400;">(Default: {{ $orgSettings->whatsapp_phone }})</span>
                            @endif
                        </label>
                        <input type="text" class="crm-input" wire:model="editWhatsappNumber" placeholder="{{ $orgSettings->whatsapp_phone ?: '+91 98765 43210' }}">
                        @error('editWhatsappNumber') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Lead Owner</label>
                        <select class="crm-select" wire:model="editAssignedUserId">
                            <option value="">Unassigned (or Round-Robin)</option>
                            @foreach($orgMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @error('editAssignedUserId') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Expires At (optional)</label>
                        <input type="datetime-local" class="crm-input" wire:model="editExpiresAt">
                        @error('editExpiresAt') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="crm-grid crm-grid-2 crm-mb-1">
                    <div class="crm-form-group">
                        <label class="crm-label">WhatsApp Pre-filled Message Template</label>
                        <input type="text" class="crm-input" wire:model="editWhatsappMessage" placeholder="Hi, I am {name} (Ref: {lead_ref}).">
                        <div class="crm-muted" style="font-size:11px;margin-top:0.25rem;">
                            Placeholders: <code>{name}</code>, <code>{phone}</code>, <code>{company}</code>, <code>{campaign}</code>, <code>{lead_ref}</code>, <code>{custom.field_key}</code>
                        </div>
                        @error('editWhatsappMessage') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group" style="display:flex;align-items:center;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                            <input type="checkbox" wire:model="editAutoAssignRoundRobin">
                            <span>Round-Robin Auto-Assign</span>
                        </label>
                    </div>
                </div>

                <div class="crm-form-group crm-mb-2" style="padding:0.85rem;border:1px solid var(--crm-border);border-radius:var(--crm-radius-sm);background:var(--crm-surface-low);">
                    <div class="crm-flex" style="justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <label class="crm-label" style="margin:0;">Attached Custom Fields on /go/{slug}</label>
                        @can('create', \App\Modules\CustomFields\Models\CustomFieldDefinition::class)
                            <button type="button" wire:click="openNewFieldForm('edit')" class="crm-btn crm-btn-secondary crm-btn-sm">
                                + New Lead Custom Field
                            </button>
                        @endcan
                    </div>

                    @if($leadCustomFields->isNotEmpty())
                        <div class="crm-grid crm-grid-2" style="gap:0.5rem;">
                            @foreach($leadCustomFields as $field)
                                <label wire:key="edit-cf-{{ $field->id }}" style="display:flex;align-items:center;gap:0.45rem;padding:0.4rem 0.6rem;border:1px solid var(--crm-border);border-radius:var(--crm-radius-sm);cursor:pointer;background:var(--crm-surface);">
                                    <input type="checkbox" value="{{ $field->id }}" wire:model="editSelectedCustomFieldIds">
                                    <span>{{ $field->label }}</span>
                                    <span class="crm-badge">{{ $field->data_type }}</span>
                                    <code style="font-size:11px;">{custom.{{ $field->field_key }}}</code>
                                    @if($field->is_required)
                                        <span class="crm-badge" style="background:var(--crm-error-container);color:var(--crm-on-error-container);">Required</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="crm-muted" style="margin:0;font-size:13px;">
                            No lead custom fields defined yet. Click <strong>+ New Lead Custom Field</strong> to create and attach one right here.
                        </p>
                    @endif

                    @if($showNewFieldForm && $newFieldTarget === 'edit')
                        <div class="crm-card crm-mt-1" style="margin-top:0.75rem;padding:0.85rem;background:var(--crm-surface);">
                            <div class="crm-flex" style="justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
                                <strong>Create &amp; Attach Lead Custom Field</strong>
                                <button type="button" wire:click="cancelNewFieldForm" class="crm-btn crm-btn-secondary crm-btn-sm">Cancel</button>
                            </div>
                            <div class="crm-grid crm-grid-2">
                                <div class="crm-form-group">
                                    <label class="crm-label">Label *</label>
                                    <input type="text" class="crm-input" wire:model="newFieldLabel" placeholder="Monthly Budget">
                                    @error('newFieldLabel') <div class="crm-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="crm-form-group">
                                    <label class="crm-label">Field Key (auto if blank)</label>
                                    <input type="text" class="crm-input" wire:model="newFieldKey" placeholder="monthly_budget">
                                    @error('newFieldKey') <div class="crm-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="crm-form-group">
                                    <label class="crm-label">Data Type</label>
                                    <select class="crm-select" wire:model.live="newFieldDataType">
                                        @foreach($customFieldDataTypes as $dt)
                                            <option value="{{ $dt }}">{{ $dt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if(in_array($newFieldDataType, ['select', 'multiselect'], true))
                                    <div class="crm-form-group">
                                        <label class="crm-label">Options (comma-separated) *</label>
                                        <input type="text" class="crm-input" wire:model="newFieldOptionsInput" placeholder="Starter, Growth, Enterprise">
                                        @error('newFieldOptionsInput') <div class="crm-error">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                                <div class="crm-form-group" style="display:flex;align-items:flex-end;">
                                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                        <input type="checkbox" wire:model="newFieldIsRequired">
                                        <span>Required on Form</span>
                                    </label>
                                </div>
                            </div>
                            <button type="button" wire:click="createInlineCustomField" class="crm-btn crm-btn-primary crm-btn-sm">
                                Save &amp; Attach Custom Field
                            </button>
                        </div>
                    @endif
                </div>

                <div class="crm-flex" style="justify-content:flex-end;gap:0.5rem;">
                    <button type="button" wire:click="cancelConfigure" class="crm-btn crm-btn-secondary">Cancel</button>
                    <button type="button" wire:click="saveCaptureConfig" class="crm-btn crm-btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    @endif

    @if($showCreateModal)
        @can('create', \App\Modules\Tracking\Models\TrackingLink::class)
            <div class="crm-modal-backdrop open">
                <div class="crm-modal" style="max-width:860px;width:95%;max-height:90vh;overflow-y:auto;">
                    <div class="crm-card-header">
                        <h3 class="crm-card-title">Create Tracking Link</h3>
                        <button type="button" wire:click="closeCreateModal" class="crm-btn crm-btn-secondary crm-btn-sm">Close</button>
                    </div>

                    <form wire:submit="createLink">
                        <div class="crm-grid crm-grid-3 crm-mb-1">
                            <div class="crm-form-group">
                                <label class="crm-label">Campaign / Link Name *</label>
                                <input type="text" class="crm-input" wire:model="name" placeholder="Spring Launch Ads">
                                @error('name') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Short Code (optional)</label>
                                <input type="text" class="crm-input" wire:model="code" placeholder="spring-ads">
                                @error('code') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Capture Slug /go/{slug} (optional)</label>
                                <input type="text" class="crm-input" wire:model="slug" placeholder="defaults to short code">
                                @error('slug') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Channel</label>
                                <select class="crm-select" wire:model="channel">
                                    @foreach($channels as $ch)
                                        <option value="{{ $ch }}">{{ ucfirst($ch) }}</option>
                                    @endforeach
                                </select>
                                @error('channel') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Destination URL (optional — defaults to Capture Form)</label>
                                <input type="url" class="crm-input" wire:model="destinationUrl" placeholder="Leave blank to open /go/{slug} capture form">
                                @error('destinationUrl') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Ad Spend</label>
                                <input type="number" step="0.01" min="0" class="crm-input" wire:model="adSpend">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">UTM Source</label>
                                <input type="text" class="crm-input" wire:model="utmSource" placeholder="meta">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">UTM Medium</label>
                                <input type="text" class="crm-input" wire:model="utmMedium" placeholder="cpc">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">UTM Campaign</label>
                                <input type="text" class="crm-input" wire:model="utmCampaign" placeholder="spring_launch">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">
                                    WhatsApp Number (optional)
                                    @if($orgSettings->whatsapp_phone)
                                        <span class="crm-muted" style="font-weight:400;">(Default: {{ $orgSettings->whatsapp_phone }})</span>
                                    @endif
                                </label>
                                <input type="text" class="crm-input" wire:model="whatsappNumber" placeholder="{{ $orgSettings->whatsapp_phone ?: '+91 98765 43210' }}">
                                @error('whatsappNumber') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Lead Owner (Auto-Assign)</label>
                                <select class="crm-select" wire:model="assignedUserId">
                                    <option value="">Unassigned (or Round-Robin)</option>
                                    @foreach($orgMembers as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                @error('assignedUserId') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="crm-grid crm-grid-2 crm-mb-1">
                            <div class="crm-form-group">
                                <label class="crm-label">WhatsApp Pre-filled Message (optional)</label>
                                <input type="text" class="crm-input" wire:model="whatsappMessage" placeholder="Hi, I am {name} (Ref: {lead_ref}).">
                                <div class="crm-muted" style="font-size:11px;margin-top:0.25rem;">
                                    Placeholders: <code>{name}</code>, <code>{phone}</code>, <code>{company}</code>, <code>{campaign}</code>, <code>{lead_ref}</code>, <code>{custom.field_key}</code>
                                </div>
                            </div>
                            <div class="crm-form-group" style="display:flex;align-items:center;">
                                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                    <input type="checkbox" wire:model="autoAssignRoundRobin">
                                    <span>Round-Robin Auto-Assign</span>
                                </label>
                            </div>
                        </div>

                        <div class="crm-form-group crm-mb-2" style="padding:0.85rem;border:1px solid var(--crm-border);border-radius:var(--crm-radius-sm);background:var(--crm-surface-low);">
                            <div class="crm-flex" style="justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <label class="crm-label" style="margin:0;">Lead Custom Fields for /go/{slug} Capture Form</label>
                                @can('create', \App\Modules\CustomFields\Models\CustomFieldDefinition::class)
                                    <button type="button" wire:click="openNewFieldForm('create')" class="crm-btn crm-btn-secondary crm-btn-sm">
                                        + New Lead Custom Field
                                    </button>
                                @endcan
                            </div>

                            @if($leadCustomFields->isNotEmpty())
                                <div class="crm-grid crm-grid-3" style="gap:0.5rem;">
                                    @foreach($leadCustomFields as $field)
                                        <label wire:key="create-cf-{{ $field->id }}" style="display:flex;align-items:center;gap:0.45rem;padding:0.4rem 0.6rem;border:1px solid var(--crm-border);border-radius:var(--crm-radius-sm);cursor:pointer;background:var(--crm-surface);">
                                            <input type="checkbox" value="{{ $field->id }}" wire:model="selectedCustomFieldIds">
                                            <span>{{ $field->label }}</span>
                                            <span class="crm-badge">{{ $field->data_type }}</span>
                                            @if($field->is_required)
                                                <span class="crm-badge" style="background:var(--crm-error-container);color:var(--crm-on-error-container);">Required</span>
                                            @else
                                                <span class="crm-muted" style="font-size:11px;">Optional</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="crm-muted" style="margin:0;font-size:13px;">
                                    No lead custom fields yet. Click <strong>+ New Lead Custom Field</strong> to create one and attach it directly to this tracking link.
                                </p>
                            @endif

                            @if($showNewFieldForm && $newFieldTarget === 'create')
                                <div class="crm-card crm-mt-1" style="margin-top:0.75rem;padding:0.85rem;background:var(--crm-surface);">
                                    <div class="crm-flex" style="justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
                                        <strong>Create &amp; Attach Lead Custom Field</strong>
                                        <button type="button" wire:click="cancelNewFieldForm" class="crm-btn crm-btn-secondary crm-btn-sm">Cancel</button>
                                    </div>
                                    <div class="crm-grid crm-grid-3">
                                        <div class="crm-form-group">
                                            <label class="crm-label">Label *</label>
                                            <input type="text" class="crm-input" wire:model="newFieldLabel" placeholder="Monthly Budget">
                                            @error('newFieldLabel') <div class="crm-error">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="crm-form-group">
                                            <label class="crm-label">Field Key (auto if blank)</label>
                                            <input type="text" class="crm-input" wire:model="newFieldKey" placeholder="monthly_budget">
                                            @error('newFieldKey') <div class="crm-error">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="crm-form-group">
                                            <label class="crm-label">Data Type</label>
                                            <select class="crm-select" wire:model.live="newFieldDataType">
                                                @foreach($customFieldDataTypes as $dt)
                                                    <option value="{{ $dt }}">{{ $dt }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if(in_array($newFieldDataType, ['select', 'multiselect'], true))
                                            <div class="crm-form-group">
                                                <label class="crm-label">Options (comma-separated) *</label>
                                                <input type="text" class="crm-input" wire:model="newFieldOptionsInput" placeholder="Starter, Growth, Enterprise">
                                                @error('newFieldOptionsInput') <div class="crm-error">{{ $message }}</div> @enderror
                                            </div>
                                        @endif
                                        <div class="crm-form-group" style="display:flex;align-items:flex-end;">
                                            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                                <input type="checkbox" wire:model="newFieldIsRequired">
                                                <span>Required on Form</span>
                                            </label>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="createInlineCustomField" class="crm-btn crm-btn-primary crm-btn-sm">
                                        Save &amp; Attach Custom Field
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="crm-flex" style="justify-content:flex-end;gap:0.5rem;">
                            <button type="button" wire:click="closeCreateModal" class="crm-btn crm-btn-secondary">Cancel</button>
                            <button type="submit" class="crm-btn crm-btn-primary">Create Tracking Link</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
</div>
