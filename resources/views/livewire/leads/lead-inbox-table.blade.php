<div>
    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">{{ __('Leads') }}</h2>
            <div class="crm-flex">
                <input type="text" class="crm-input" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search leads...') }}" style="width:240px;">
                <select class="crm-select" wire:model.live="typeFilter" style="width:auto;">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <select class="crm-select" wire:model.live="statusFilter" style="width:auto;">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($statusMessage)
            <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
        @endif

        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>Mode</th>
                        <th>{{ __('Company Name') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td>
                                <a href="{{ route('leads.show', $lead) }}"><strong>{{ $lead->fullName() }}</strong></a>
                                <div class="crm-muted">Source: {{ $lead->source }}</div>
                            </td>
                            <td>
                                <span class="crm-badge {{ $lead->isB2B() ? 'crm-badge-b2b' : 'crm-badge-b2c' }}">{{ $lead->type }}</span>
                            </td>
                            <td>
                                @if($lead->isB2B())
                                    <strong>{{ $lead->company_name }}</strong>
                                    @if($lead->job_title)
                                        <div class="crm-muted">{{ $lead->job_title }}</div>
                                    @endif
                                @else
                                    {{ $lead->location ?: '—' }}
                                @endif
                            </td>
                            <td>
                                <div>{{ $lead->email ?: '—' }}</div>
                                <div class="crm-muted">{{ $lead->phone ?: '' }}</div>
                            </td>
                            <td><span class="crm-badge">{{ ucfirst($lead->status) }}</span></td>
                            <td>{{ $lead->assignedUser?->name ?? 'Unassigned' }}</td>
                            <td class="crm-flex">
                                <button type="button" wire:click="openLead({{ $lead->id }})" class="crm-btn crm-btn-secondary crm-btn-sm">View</button>
                                @if(! $lead->isConverted())
                                    @can('convert', $lead)
                                        <button type="button" wire:click="openConvert({{ $lead->id }})" class="crm-btn crm-btn-primary crm-btn-sm">Convert</button>
                                    @endcan
                                @endif
                                @can('delete', $lead)
                                    <button type="button" wire:click="deleteLead({{ $lead->id }})" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="crm-muted">No leads found matching your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
