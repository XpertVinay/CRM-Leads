@extends('layouts.crm')

@section('title', 'Dashboard — ' . $organization->name)

@section('content')
    <div class="crm-page-header">
        <div>
            <div class="crm-flex crm-mb-1">
                <span class="crm-muted" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">
                    {{ now()->timezone($organization->timezone)->format('l, M j') }}
                </span>
                <span class="crm-badge crm-badge-{{ $role }}">{{ $role }}</span>
            </div>
            <h1 class="crm-page-title">WhatsApp B2B CRM Dashboard</h1>
            <p class="crm-muted" style="margin: 0;">
                Organization: <strong>{{ $organization->name }}</strong> &middot; Timezone: {{ $organization->timezone }} &middot; Currency: {{ $organization->currency }}
            </p>
        </div>
        <div class="crm-flex">
            <a href="{{ route('reports.export') }}" class="crm-btn crm-btn-secondary">
                <span class="material-symbols-outlined" style="font-size: 18px;">file_download</span>
                <span>Export Report</span>
            </a>
            <a href="{{ route('leads.index') }}" class="crm-btn crm-btn-primary">
                <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span>
                <span>New Lead</span>
            </a>
        </div>
    </div>

    <div class="crm-card crm-mb-2">
        <form id="dashboard-filter-form" class="crm-toolbar">
            <input type="date" name="date_from" title="From Date">
            <input type="date" name="date_to" title="To Date">
            <select name="campaign_id">
                <option value="">All Campaigns</option>
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                @endforeach
            </select>
            <input type="text" name="source" placeholder="Source (e.g. facebook)">
            <select name="assigned_user_id">
                <option value="">All Employees</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st }}">{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm">Apply Filters</button>
            <button type="button" id="dashboard-filter-reset" class="crm-btn crm-btn-secondary crm-btn-sm">Reset</button>
        </form>
    </div>

    <div class="crm-kpi-grid" id="dashboard-kpis">
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Total Leads</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">groups</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="total_leads">{{ $metrics['kpis']['total_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">New Leads</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">fiber_new</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="new_leads">{{ $metrics['kpis']['new_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Today's Leads</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">today</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="todays_leads">{{ $metrics['kpis']['todays_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Monthly Leads</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">calendar_month</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="monthly_leads">{{ $metrics['kpis']['monthly_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Unassigned Leads</span>
                <span class="crm-kpi-icon" style="color: var(--crm-tertiary);"><span class="material-symbols-outlined" style="font-size:20px;">person_off</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="unassigned_leads">{{ $metrics['kpis']['unassigned_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Follow-ups Due</span>
                <span class="crm-kpi-icon" style="color: var(--crm-tertiary);"><span class="material-symbols-outlined" style="font-size:20px;">event_upcoming</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="follow_ups_due">{{ $metrics['kpis']['follow_ups_due'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Qualified Leads</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">verified</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="qualified_leads">{{ $metrics['kpis']['qualified_leads'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Won Deals</span>
                <span class="crm-kpi-icon" style="color: var(--crm-success);"><span class="material-symbols-outlined" style="font-size:20px;">emoji_events</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="won_deals">{{ $metrics['kpis']['won_deals'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Lost Deals</span>
                <span class="crm-kpi-icon" style="color: var(--crm-danger);"><span class="material-symbols-outlined" style="font-size:20px;">cancel</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="lost_deals">{{ $metrics['kpis']['lost_deals'] }}</div>
        </div>
        <div class="crm-kpi-card">
            <div class="crm-kpi-head">
                <span class="crm-kpi-label">Conversion Rate</span>
                <span class="crm-kpi-icon"><span class="material-symbols-outlined" style="font-size:20px;">trending_up</span></span>
            </div>
            <div class="crm-kpi-value" data-kpi="conversion_rate">{{ $metrics['kpis']['conversion_rate'] }}%</div>
        </div>
    </div>

    <div class="crm-chart-grid">
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Leads Over Time</h3>
            </div>
            <div id="chart-leads-over-time"></div>
        </div>
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Lead Conversion Funnel</h3>
            </div>
            <div id="chart-conversion-funnel"></div>
        </div>
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Leads by Source</h3>
            </div>
            <div id="chart-leads-by-source"></div>
        </div>
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Leads by Employee</h3>
            </div>
            <div id="chart-leads-by-employee"></div>
        </div>
    </div>

    <div class="crm-chart-grid">
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Overdue Follow-ups</h3>
            </div>
            <div id="widget-overdue-followups">
                @forelse($metrics['follow_ups']['overdue'] as $fu)
                    <div class="crm-list-item">
                        <span><strong>{{ $fu->lead?->fullName() ?? 'Lead' }}</strong> <span class="crm-muted">({{ $fu->type }})</span></span>
                        <span class="crm-badge crm-badge-lost">{{ $fu->due_at->timezone($organization->timezone)->format('M d, H:i') }}</span>
                    </div>
                @empty
                    <p class="crm-muted">No overdue follow-ups.</p>
                @endforelse
            </div>
        </div>
        <div class="crm-card">
            <div class="crm-card-header">
                <h3 class="crm-card-title">Today's &amp; Upcoming Follow-ups</h3>
            </div>
            <div id="widget-today-followups">
                @forelse($metrics['follow_ups']['today'] as $fu)
                    <div class="crm-list-item">
                        <span><strong>{{ $fu->lead?->fullName() ?? 'Lead' }}</strong> <span class="crm-muted">({{ $fu->type }})</span></span>
                        <span class="crm-badge crm-badge-open">Today {{ $fu->due_at->timezone($organization->timezone)->format('H:i') }}</span>
                    </div>
                @empty
                    <p class="crm-muted">No follow-ups due today.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header">
            <h3 class="crm-card-title">Campaign Performance</h3>
            <a href="{{ route('campaigns.index') }}" class="crm-muted" style="font-weight: 600; font-size: 0.82rem;">See all &rsaquo;</a>
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th>Clicks</th>
                        <th>Leads</th>
                        <th>Qualified</th>
                        <th>Won</th>
                        <th>Conv. Rate</th>
                    </tr>
                </thead>
                <tbody id="dashboard-campaign-rows">
                    @forelse($metrics['campaign_performance'] as $cp)
                        <tr>
                            <td><a href="{{ route('campaigns.show', $cp['id']) }}" style="font-weight: 600;">{{ $cp['name'] }}</a></td>
                            <td>{{ $cp['platform'] }}</td>
                            <td><span class="crm-badge">{{ $cp['status'] }}</span></td>
                            <td>{{ $cp['metrics']['clicks'] ?? 0 }}</td>
                            <td>{{ $cp['metrics']['leads'] ?? 0 }}</td>
                            <td>{{ $cp['metrics']['qualified_leads'] ?? 0 }}</td>
                            <td>{{ $cp['metrics']['won_deals'] ?? 0 }}</td>
                            <td><strong>{{ $cp['metrics']['conversion_rate'] ?? 0 }}%</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="crm-muted">No campaigns created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var initialMetrics = @json($metrics);

    function renderDashboard(data) {
        $.each(data.kpis || {}, function (key, val) {
            var display = key === 'conversion_rate' ? (val + '%') : val;
            $('[data-kpi="' + key + '"]').text(display);
        });
        CrmCharts.renderBars('#chart-leads-over-time', (data.charts || {}).leads_over_time, '#4f46e5');
        CrmCharts.renderBars('#chart-conversion-funnel', (data.charts || {}).conversion_funnel, '#059669');
        CrmCharts.renderBars('#chart-leads-by-source', (data.charts || {}).leads_by_source, '#3525cd');
        CrmCharts.renderBars('#chart-leads-by-employee', (data.charts || {}).leads_by_employee, '#a44100');
    }

    renderDashboard(initialMetrics);

    $('#dashboard-filter-form').on('submit', function (e) {
        e.preventDefault();
        $.get('/ajax/dashboard/metrics', $(this).serialize(), function (resp) {
            renderDashboard(resp.data);
        });
    });

    $('#dashboard-filter-reset').on('click', function () {
        $('#dashboard-filter-form')[0].reset();
        $('#dashboard-filter-form').trigger('submit');
    });
});
</script>
@endpush
