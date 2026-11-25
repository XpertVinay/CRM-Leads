<div>
    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Campaign Tracking Links</h2>
            <div class="crm-flex">
                <label class="crm-label" style="margin:0;">Attribution Model:</label>
                <select class="crm-select" wire:model.live="attributionModel" style="width:auto;">
                    @foreach($attributionModels as $model)
                        <option value="{{ $model }}">{{ ucwords(str_replace('_', ' ', $model)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($statusMessage)
            <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
        @endif

        @can('create', \App\Modules\Tracking\Models\TrackingLink::class)
            <form wire:submit="createLink" class="crm-mb-2">
                <div class="crm-grid crm-grid-3">
                    <div class="crm-form-group">
                        <label class="crm-label">Campaign Name</label>
                        <input type="text" class="crm-input" wire:model="name" placeholder="Spring Launch Ads">
                        @error('name') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Short Code (optional)</label>
                        <input type="text" class="crm-input" wire:model="code" placeholder="spring-ads">
                        @error('code') <div class="crm-error">{{ $message }}</div> @enderror
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
                        <label class="crm-label">Destination URL</label>
                        <input type="url" class="crm-input" wire:model="destinationUrl" placeholder="https://example.com/offer">
                        @error('destinationUrl') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">UTM Source</label>
                        <input type="text" class="crm-input" wire:model="utmSource" placeholder="meta">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Ad Spend</label>
                        <input type="number" step="0.01" min="0" class="crm-input" wire:model="adSpend">
                    </div>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Create Tracking Link</button>
            </form>
        @endcan

        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Short URL</th>
                        <th>Channel</th>
                        <th>Clicks (Raw)</th>
                        <th>Leads Captured</th>
                        <th>Won Deals</th>
                        <th>Attributed Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($links as $link)
                        @php($metrics = $summary->get($link->id, []))
                        <tr>
                            <td>
                                <strong>{{ $link->name }}</strong>
                                <div class="crm-muted">{{ $link->destination_url }}</div>
                            </td>
                            <td><code>{{ $link->shortUrl() }}</code></td>
                            <td><span class="crm-badge">{{ $link->channel }}</span></td>
                            <td>{{ $link->clicks_count }}</td>
                            <td>{{ $metrics['leads_count'] ?? 0 }}</td>
                            <td>{{ $metrics['won_deals_count'] ?? 0 }}</td>
                            <td>${{ number_format((float) ($metrics['attributed_revenue'] ?? 0), 2) }}</td>
                            <td>
                                <span class="crm-badge {{ $link->is_active ? 'crm-badge-active' : 'crm-badge-suspended' }}">
                                    {{ $link->is_active ? 'Active' : 'Paused' }}
                                </span>
                            </td>
                            <td class="crm-flex">
                                @can('update', $link)
                                    <button type="button" wire:click="toggleActive({{ $link->id }})" class="crm-btn crm-btn-secondary crm-btn-sm">
                                        {{ $link->is_active ? 'Pause' : 'Activate' }}
                                    </button>
                                @endcan
                                @can('delete', $link)
                                    <button type="button" wire:click="deleteLink({{ $link->id }})" class="crm-btn crm-btn-danger crm-btn-sm">
                                        Delete
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="crm-muted">No tracking links created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
