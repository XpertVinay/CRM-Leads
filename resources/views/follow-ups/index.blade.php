@extends('layouts.crm')

@section('title', 'Follow-ups — CRM')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">Follow-ups</h1>
            <p class="crm-muted">Manage overdue, today's, and upcoming lead follow-ups.</p>
        </div>
        <button type="button" class="crm-btn crm-btn-primary" onclick="CrmUi.openModal('#followup-modal')">+ Schedule Follow-up</button>
    </div>

    <div class="crm-kpi-grid">
        <div class="crm-kpi-card"><div class="crm-kpi-label">Overdue</div><div class="crm-kpi-value" id="fu-count-overdue">0</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Due Today</div><div class="crm-kpi-value" id="fu-count-today">0</div></div>
        <div class="crm-kpi-card"><div class="crm-kpi-label">Upcoming</div><div class="crm-kpi-value" id="fu-count-upcoming">0</div></div>
    </div>

    <div class="crm-card crm-mb-2">
        <div class="crm-toolbar">
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm fu-bucket" data-bucket="">All</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm fu-bucket" data-bucket="overdue">Overdue</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm fu-bucket" data-bucket="today">Today</button>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm fu-bucket" data-bucket="upcoming">Upcoming</button>
        </div>
    </div>

    <div class="crm-card">
        <table class="crm-table">
            <thead>
                <tr><th>Lead</th><th>Type</th><th>Due At</th><th>Assigned To</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
            </thead>
            <tbody id="followups-tbody">
                <tr><td colspan="7" class="crm-muted">Loading follow-ups...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="followup-modal" class="crm-modal-backdrop">
        <div class="crm-modal">
            <div class="crm-between crm-mb-2">
                <h3>Schedule Follow-up</h3>
                <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" data-crm-modal-close>Close</button>
            </div>
            <div id="followup-form-errors"></div>
            <form id="followup-create-form">
                <div class="crm-field">
                    <label>Lead</label>
                    <select name="lead_id" required>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->lead_reference }} — {{ $lead->fullName() }} ({{ $lead->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-form-row">
                    <div class="crm-field">
                        <label>Type</label>
                        <select name="type">
                            @foreach($types as $t)
                                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="crm-field">
                        <label>Assigned Employee</label>
                        <select name="assigned_user_id">
                            @foreach($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="crm-field"><label>Due Date &amp; Time</label><input type="datetime-local" name="due_at" required></div>
                <div class="crm-field"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <button type="submit" class="crm-btn crm-btn-primary">Save Follow-up</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var activeBucket = '';

    function loadFollowUps() {
        $.get('/ajax/follow-ups', { bucket: activeBucket }, function (resp) {
            var summary = resp.summary || {};
            $('#fu-count-overdue').text(summary.overdue || 0);
            $('#fu-count-today').text(summary.today || 0);
            $('#fu-count-upcoming').text(summary.upcoming || 0);

            var rows = resp.data || [];
            if (!rows.length) {
                $('#followups-tbody').html('<tr><td colspan="7" class="crm-muted">No follow-ups found.</td></tr>');
                return;
            }
            var html = rows.map(function (f) {
                var leadName = f.lead ? ((f.lead.lead_reference ? '[' + f.lead.lead_reference + '] ' : '') + f.lead.first_name) : 'Lead #' + f.lead_id;
                var owner = f.assigned_user ? f.assigned_user.name : 'Unassigned';
                var actionBtn = f.status === 'pending'
                    ? '<button type="button" class="crm-btn crm-btn-primary crm-btn-sm fu-complete" data-id="' + f.id + '">Mark Complete</button>'
                    : '';
                return '<tr>' +
                    '<td><a href="/leads/' + f.lead_id + '">' + $('<div>').text(leadName).html() + '</a></td>' +
                    '<td>' + f.type + '</td>' +
                    '<td>' + new Date(f.due_at).toLocaleString() + '</td>' +
                    '<td>' + $('<div>').text(owner).html() + '</td>' +
                    '<td><span class="crm-badge">' + f.status + '</span></td>' +
                    '<td>' + $('<div>').text(f.notes || '').html() + '</td>' +
                    '<td>' + actionBtn + '</td>' +
                    '</tr>';
            }).join('');
            $('#followups-tbody').html(html);
        });
    }

    $('.fu-bucket').on('click', function () {
        activeBucket = $(this).data('bucket');
        loadFollowUps();
    });

    $('#followup-create-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/follow-ups', $(this).serialize())
            .done(function () {
                CrmUi.closeModal('#followup-modal');
                $('#followup-create-form')[0].reset();
                CrmUi.flash('Follow-up scheduled.');
                loadFollowUps();
            })
            .fail(function (xhr) {
                CrmUi.renderErrors($('#followup-form-errors'), xhr);
            });
    });

    $(document).on('click', '.fu-complete', function () {
        var id = $(this).data('id');
        $.ajax({
            url: '/ajax/follow-ups/' + id,
            method: 'PATCH',
            data: { status: 'completed', outcome: 'Completed' }
        }).done(function () {
            CrmUi.flash('Follow-up marked completed.');
            loadFollowUps();
        });
    });

    loadFollowUps();
});
</script>
@endpush
