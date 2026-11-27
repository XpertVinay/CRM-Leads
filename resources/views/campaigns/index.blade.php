@extends('layouts.crm')

@section('title', 'Campaigns — CRM')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">Campaigns</h1>
            <p class="crm-muted">Track marketing campaigns, budgets, WhatsApp tracking links, and ROI.</p>
        </div>
        <button type="button" class="crm-btn crm-btn-primary" onclick="CrmUi.openModal('#campaign-modal')">+ New
            Campaign</button>
    </div>

    <div class="crm-card">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Platform</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Clicks</th>
                    <th>Leads</th>
                    <th>Won</th>
                    <th>Conv. %</th>
                </tr>
            </thead>
            <tbody id="campaigns-tbody">
                <tr>
                    <td colspan="8" class="crm-muted">Loading campaigns...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="campaign-modal" class="crm-modal-backdrop">
        <div class="crm-modal">
            <div class="crm-between crm-mb-2">
                <h3>Create Campaign</h3>
                <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" data-crm-modal-close>Close</button>
            </div>
            <div id="campaign-form-errors"></div>
            <form id="campaign-create-form">
                <div class="crm-field"><label>Campaign Name</label><input name="name" required></div>
                <div class="crm-form-row">
                    <div class="crm-field">
                        <label>Type</label>
                        <select name="type">
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="crm-field">
                        <label>Platform</label>
                        <select name="platform">
                            @foreach($platforms as $platform)
                                <option value="{{ $platform }}">{{ ucwords(str_replace('_', ' ', $platform)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="crm-form-row">
                    <div class="crm-field"><label>Budget</label><input name="budget" type="number" step="0.01" min="0">
                    </div>
                    <div class="crm-field">
                        <label>Status</label>
                        <select name="status">
                            @foreach($statuses as $st)
                                <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="crm-form-row">
                    <div class="crm-field"><label>Start Date</label><input name="starts_at" type="date"></div>
                    <div class="crm-field"><label>End Date</label><input name="ends_at" type="date"></div>
                </div>
                <div class="crm-field"><label>Description</label><textarea name="description" rows="2"></textarea></div>
                <button type="submit" class="crm-btn crm-btn-primary">Create Campaign</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            function loadCampaigns() {
                $.get('/ajax/campaigns', function (resp) {
                    var rows = resp.data || [];
                    if (!rows.length) {
                        $('#campaigns-tbody').html('<tr><td colspan="8" class="crm-muted">No campaigns yet.</td></tr>');
                        return;
                    }
                    var html = rows.map(function (c) {
                        var m = c.metrics || {};
                        return '<tr>' +
                            '<td><a href="/campaigns/' + encodeURIComponent(c.id) + '"><strong>' + CRM.escapeHtml(c.name) + '</strong></a></td>' +
                            '<td>' + CRM.escapeHtml(c.platform) + '</td>' +
                            '<td>' + CRM.escapeHtml(c.type) + '</td>' +
                            '<td><span class="crm-badge">' + CRM.escapeHtml(c.status) + '</span></td>' +
                            '<td>' + (m.clicks || 0) + '</td>' +
                            '<td>' + (m.leads || 0) + '</td>' +
                            '<td>' + (m.won_deals || 0) + '</td>' +
                            '<td>' + (m.conversion_rate || 0) + '%</td>' +
                            '</tr>';
                    }).join('');
                    $('#campaigns-tbody').html(html);
                });
            }

            $('#campaign-create-form').on('submit', function (e) {
                e.preventDefault();
                $.post('/ajax/campaigns', $(this).serialize())
                    .done(function () {
                        CrmUi.closeModal('#campaign-modal');
                        $('#campaign-create-form')[0].reset();
                        CrmUi.flash('Campaign created successfully.');
                        loadCampaigns();
                    })
                    .fail(function (xhr) {
                        CrmUi.renderErrors($('#campaign-form-errors'), xhr);
                    });
            });

            loadCampaigns();
        });
    </script>
@endpush