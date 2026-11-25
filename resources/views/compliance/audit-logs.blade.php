@extends('layouts.crm')

@section('title', 'Audit Logs')

@section('content')
<div class="crm-page-header">
    <h1>Audit Logs</h1>
</div>

<div class="crm-card">
    <table class="crm-table" id="audit-logs-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Target</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody id="audit-logs-tbody"></tbody>
    </table>
</div>

@push('scripts')
<script>
$(function() {
    $.get('{{ route("ajax.audit-logs.index") }}', function(resp) {
        var tbody = $('#audit-logs-tbody');
        tbody.empty();
        (resp.data || []).forEach(function(log) {
            tbody.append(
                '<tr>' +
                '<td>' + new Date(log.created_at).toLocaleString() + '</td>' +
                '<td>' + (log.user ? log.user.name : 'System') + '</td>' +
                '<td><span class="crm-badge">' + log.action + '</span></td>' +
                '<td>' + (log.auditable_type ? log.auditable_type + ' #' + log.auditable_id : '—') + '</td>' +
                '<td>' + (log.ip_address || '—') + '</td>' +
                '</tr>'
            );
        });
    });
});
</script>
@endpush
@endsection
