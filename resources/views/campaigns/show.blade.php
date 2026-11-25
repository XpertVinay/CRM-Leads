@extends('layouts.crm')

@section('title', $campaign->name . ' — Campaign')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">{{ $campaign->name }}</h1>
            <p class="crm-muted">{{ ucfirst($campaign->platform) }} &middot; {{ ucfirst($campaign->type) }} &middot; Status: {{ ucfirst($campaign->status) }}</p>
        </div>
        <a href="{{ route('campaigns.index') }}" class="crm-btn crm-btn-secondary">Back to Campaigns</a>
    </div>

    <div class="crm-kpi-grid">
        <div class="crm-kpi-card"><div class="crm-kpi-label">Clicks</div><div class="crm-kpi-value">{{ $metrics['clicks'] }}</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Unique Visitors</div><div class="crm-kpi-value">{{ $metrics['unique_visitors'] }}</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Captured Leads</div><div class="crm-kpi-value">{{ $metrics['leads'] }}</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Qualified Leads</div><div class="crm-kpi-value">{{ $metrics['qualified_leads'] }}</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Won Deals</div><div class="crm-kpi-value">{{ $metrics['won_deals'] }}</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Conversion Rate</div><div class="crm-kpi-value">{{ $metrics['conversion_rate'] }}%</div></div>
    </div>

    <div class="crm-card">
        <h3>Tracking Links ({{ $campaign->trackingLinks->count() }})</h3>
        <table class="crm-table">
            <thead>
                <tr><th>Name</th><th>Capture URL (/go/...)</th><th>Channel</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($campaign->trackingLinks as $link)
                    <tr>
                        <td>{{ $link->name }}</td>
                        <td><code>{{ $link->captureUrl() }}</code></td>
                        <td>{{ $link->channel }}</td>
                        <td><span class="crm-badge">{{ $link->isUsable() ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="crm-muted">No tracking links attached to this campaign yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
