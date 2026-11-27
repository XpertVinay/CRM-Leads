@extends('layouts.crm')

@section('title', 'Audit Logs')

@section('content')
    <div class="crm-page-header">
        <h1>Audit Logs</h1>
        @if(auth()->user()?->isSuperadmin())
            <button type="button" class="crm-btn crm-btn-primary" onclick="CRM.openAuditLogModal()">+ New Audit Log</button>
        @endif
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
                    @if(auth()->user()?->isSuperadmin())
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody id="audit-logs-tbody"></tbody>
        </table>
    </div>

    @if(auth()->user()?->isSuperadmin())
        <div id="audit-log-modal" class="crm-modal-backdrop">
            <div class="crm-modal">
                <div class="crm-modal-header">
                    <h2 id="audit-log-modal-title">New Audit Log</h2>
                    <button type="button" class="crm-modal-close" onclick="CRM.closeModal('audit-log-modal')">&times;</button>
                </div>
                <form id="audit-log-form" onsubmit="return CRM.submitAuditLog(event)">
                    <input type="hidden" id="audit-log-id">
                    <div class="crm-form-group">
                        <label class="crm-label">Action *</label>
                        <input type="text" id="audit-log-action" required class="crm-input" placeholder="manual_review">
                    </div>
                    <div class="crm-form-row">
                        <div class="crm-form-group">
                            <label class="crm-label">Target Type</label>
                            <input type="text" id="audit-log-type" class="crm-input" placeholder="Contact">
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Target ID</label>
                            <input type="number" id="audit-log-target-id" class="crm-input">
                        </div>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">IP Address</label>
                        <input type="text" id="audit-log-ip" class="crm-input" placeholder="127.0.0.1">
                    </div>
                    <div class="crm-modal-footer">
                        <button type="button" class="crm-btn crm-btn-secondary"
                            onclick="CRM.closeModal('audit-log-modal')">Cancel</button>
                        <button type="submit" class="crm-btn crm-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            CRM.isSuperadmin = {{ auth()->user()?->isSuperadmin() ? 'true' : 'false' }};
            CRM.loadAuditLogs = function () {
                $.get('{{ route("ajax.audit-logs.index") }}', function (resp) {
                    var tbody = $('#audit-logs-tbody');
                    tbody.empty();
                    var esc = CRM.escapeHtml;
                    (resp.data || []).forEach(function (log) {
                        var $row = $('<tr></tr>');
                        $row.append('<td>' + esc(new Date(log.created_at).toLocaleString()) + '</td>');
                        $row.append('<td>' + esc(log.user ? log.user.name : 'System') + '</td>');
                        $row.append('<td><span class="crm-badge">' + esc(log.action) + '</span></td>');
                        $row.append('<td>' + esc(log.auditable_type ? log.auditable_type + ' #' + log.auditable_id : '—') + '</td>');
                        $row.append('<td>' + esc(log.ip_address || '—') + '</td>');
                        if (CRM.isSuperadmin) {
                            var $actions = $('<td></td>');
                            var $editBtn = $('<button type="button" class="crm-btn crm-btn-secondary crm-btn-sm">Edit</button>').on('click', function () {
                                CRM.openAuditLogModal(log);
                            });
                            var $delBtn = $('<button type="button" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>').on('click', function () {
                                CRM.deleteAuditLog(log.id);
                            });
                            $actions.append($editBtn).append(' ').append($delBtn);
                            $row.append($actions);
                        }
                        tbody.append($row);
                    });
                });
            };

            CRM.openAuditLogModal = function (log) {
                document.getElementById('audit-log-id').value = log ? log.id : '';
                document.getElementById('audit-log-modal-title').textContent = log ? 'Edit Audit Log' : 'New Audit Log';
                document.getElementById('audit-log-action').value = log ? (log.action || '') : '';
                document.getElementById('audit-log-type').value = log ? (log.auditable_type || '') : '';
                document.getElementById('audit-log-target-id').value = log && log.auditable_id ? log.auditable_id : '';
                document.getElementById('audit-log-ip').value = log ? (log.ip_address || '') : '';
                CRM.modal('audit-log-modal');
            };

            CRM.submitAuditLog = function (e) {
                e.preventDefault();
                var id = document.getElementById('audit-log-id').value;
                var targetId = document.getElementById('audit-log-target-id').value;
                $.ajax({
                    url: id ? '/ajax/audit-logs/' + id : '{{ route("ajax.audit-logs.store") }}',
                    method: id ? 'PATCH' : 'POST',
                    data: JSON.stringify({
                        action: document.getElementById('audit-log-action').value,
                        auditable_type: document.getElementById('audit-log-type').value || null,
                        auditable_id: targetId ? parseInt(targetId) : null,
                        ip_address: document.getElementById('audit-log-ip').value || null
                    }),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
                    success: function () {
                        CRM.closeModal('audit-log-modal');
                        CRM.flash('Audit log saved.', 'success');
                        CRM.loadAuditLogs();
                    },
                    error: function (xhr) { CRM.flash(xhr.responseJSON?.message || 'Error saving audit log.', 'error'); }
                });
                return false;
            };

            CRM.deleteAuditLog = function (id) {
                if (!confirm('Delete this audit log?')) return;
                $.ajax({
                    url: '/ajax/audit-logs/' + id,
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
                    success: function () {
                        CRM.flash('Audit log deleted.', 'success');
                        CRM.loadAuditLogs();
                    },
                    error: function (xhr) { CRM.flash(xhr.responseJSON?.message || 'Error deleting audit log.', 'error'); }
                });
            };

            $(function () { CRM.loadAuditLogs(); });
        </script>
    @endpush
@endsection