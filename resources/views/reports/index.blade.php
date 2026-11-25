@extends('layouts.crm')

@section('title', 'Reports — CRM')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">Reports &amp; Exports</h1>
            <p class="crm-muted">Organization-scoped acquisition, campaign, employee, conversion, and follow-up reports.</p>
        </div>
        <div class="crm-toolbar">
            <a href="{{ route('reports.export', ['type' => 'campaigns']) }}" class="crm-btn crm-btn-secondary">Export Campaigns CSV</a>
            <a href="{{ route('reports.export', ['type' => 'employees']) }}" class="crm-btn crm-btn-secondary">Export Employees CSV</a>
            <a href="{{ route('leads.export') }}" class="crm-btn crm-btn-primary">Export Leads CSV</a>
        </div>
    </div>

    <div class="crm-card crm-mb-2">
        <div class="crm-toolbar">
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm report-tab" data-type="campaigns">Campaign Performance</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm report-tab" data-type="employees">Employee Performance</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm report-tab" data-type="acquisition">Lead Acquisition &amp; Conversion</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm report-tab" data-type="follow_ups">Follow-up Summary</button>
        </div>
    </div>

    <div class="crm-card" id="report-output">
        <p class="crm-muted">Loading report...</p>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    function loadReport(type) {
        $.get('/ajax/reports', { type: type }, function (resp) {
            var data = resp.data;
            if (type === 'campaigns') {
                var html = '<table class="crm-table"><thead><tr><th>Campaign</th><th>Platform</th><th>Clicks</th><th>Leads</th><th>Qualified</th><th>Won</th><th>Conv %</th><th>Revenue</th></tr></thead><tbody>';
                if (!data.length) html += '<tr><td colspan="8" class="crm-muted">No campaigns found.</td></tr>';
                $.each(data, function (_i, c) {
                    var m = c.metrics || {};
                    html += '<tr><td>' + $('<div>').text(c.name).html() + '</td><td>' + c.platform + '</td><td>' + (m.clicks || 0) + '</td><td>' + (m.leads || 0) + '</td><td>' + (m.qualified_leads || 0) + '</td><td>' + (m.won_deals || 0) + '</td><td>' + (m.conversion_rate || 0) + '%</td><td>' + (m.revenue || 0) + '</td></tr>';
                });
                html += '</tbody></table>';
                $('#report-output').html(html);
            } else if (type === 'employees') {
                var html = '<table class="crm-table"><thead><tr><th>Employee</th><th>Leads</th><th>Won Deals</th><th>Revenue</th><th>Activities</th></tr></thead><tbody>';
                if (!data.length) html += '<tr><td colspan="5" class="crm-muted">No employee metrics found.</td></tr>';
                $.each(data, function (_i, r) {
                    html += '<tr><td>' + $('<div>').text(r.user_name || r.name || 'Member').html() + '</td><td>' + (r.leads_count || 0) + '</td><td>' + (r.won_deals || 0) + '</td><td>' + (r.won_revenue || 0) + '</td><td>' + (r.activities_count || 0) + '</td></tr>';
                });
                html += '</tbody></table>';
                $('#report-output').html(html);
            } else if (type === 'follow_ups') {
                $('#report-output').html('<div class="crm-kpi-grid">' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Overdue</div><div class="crm-kpi-value">' + (data.overdue || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Due Today</div><div class="crm-kpi-value">' + (data.today || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Upcoming</div><div class="crm-kpi-value">' + (data.upcoming || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Completed</div><div class="crm-kpi-value">' + (data.completed || 0) + '</div></div>' +
                    '</div>');
            } else {
                var k = data.kpis || {};
                $('#report-output').html('<div class="crm-kpi-grid">' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Total Leads</div><div class="crm-kpi-value">' + (k.total_leads || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Qualified</div><div class="crm-kpi-value">' + (k.qualified_leads || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Won Deals</div><div class="crm-kpi-value">' + (k.won_deals || 0) + '</div></div>' +
                    '<div class="crm-kpi-card"><div class="crm-kpi-label">Conversion Rate</div><div class="crm-kpi-value">' + (k.conversion_rate || 0) + '%</div></div>' +
                    '</div>');
            }
        });
    }

    $('.report-tab').on('click', function () {
        loadReport($(this).data('type'));
    });

    loadReport('campaigns');
});
</script>
@endpush
