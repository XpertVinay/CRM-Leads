@extends('layouts.crm')

@section('title', 'Pipeline Settings')

@section('content')
<div class="crm-page-header">
    <h1>Pipelines</h1>
    <button class="crm-btn crm-btn-primary" onclick="CRM.modal('create-pipeline-modal')">+ New Pipeline</button>
</div>

<div class="crm-card">
    <table class="crm-table" id="pipelines-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Default</th>
                <th>Stages</th>
                <th>Deals</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="pipelines-tbody"></tbody>
    </table>
</div>

<!-- Create Pipeline Modal -->
<div id="create-pipeline-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>New Pipeline</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('create-pipeline-modal')">&times;</button>
        </div>
        <form id="create-pipeline-form" onsubmit="return CRM.submitPipelineForm(event)">
            <div class="crm-form-group">
                <label for="pipeline-name">Pipeline Name *</label>
                <input type="text" id="pipeline-name" name="name" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label><input type="checkbox" name="is_default" value="1"> Default Pipeline</label>
            </div>
            <div id="stages-container" class="crm-mb-1">
                <label class="crm-label">Stages</label>
                <div class="stage-row">
                    <input type="text" name="stages[0][name]" placeholder="Stage name" required class="crm-input">
                    <select name="stages[0][category]" class="crm-select">
                        <option value="open">Open</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                    <input type="number" name="stages[0][stale_days]" placeholder="Stale days" class="crm-input">
                </div>
            </div>
            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" onclick="CRM.addStageRow()">+ Add Stage</button>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('create-pipeline-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Create Pipeline</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Pipeline Modal -->
<div id="edit-pipeline-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>Edit Pipeline</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('edit-pipeline-modal')">&times;</button>
        </div>
        <form id="edit-pipeline-form" onsubmit="return CRM.submitEditPipelineForm(event)">
            <input type="hidden" id="edit-pipeline-id">
            <div class="crm-form-group">
                <label for="edit-pipeline-name">Pipeline Name *</label>
                <input type="text" id="edit-pipeline-name" name="name" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label><input type="checkbox" id="edit-pipeline-default" name="is_default" value="1"> Default Pipeline</label>
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('edit-pipeline-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
CRM.loadPipelines = function() {
    $.get('{{ route("ajax.pipelines.index") }}', function(resp) {
        var tbody = $('#pipelines-tbody');
        tbody.empty();
        (resp.data || []).forEach(function(p) {
            tbody.append(
                '<tr>' +
                '<td>' + p.name + '</td>' +
                '<td>' + (p.is_default ? '✓' : '') + '</td>' +
                '<td>' + (p.stages ? p.stages.length : 0) + '</td>' +
                '<td>' + (p.deals_count || 0) + '</td>' +
                '<td>' +
                    '<button class="crm-btn crm-btn-sm crm-btn-secondary" onclick="CRM.openEditPipeline(' + p.id + ', ' + JSON.stringify(p.name) + ', ' + (p.is_default ? 'true' : 'false') + ')">Edit</button> ' +
                    '<button class="crm-btn crm-btn-sm crm-btn-danger" onclick="CRM.deletePipeline(' + p.id + ')">Delete</button>' +
                '</td>' +
                '</tr>'
            );
        });
    });
};

CRM.stageIndex = 1;
CRM.addStageRow = function() {
    var idx = CRM.stageIndex++;
    $('#stages-container').append(
        '<div class="stage-row">' +
        '<input type="text" name="stages[' + idx + '][name]" placeholder="Stage name" required class="crm-input">' +
        '<select name="stages[' + idx + '][category]" class="crm-select"><option value="open">Open</option><option value="won">Won</option><option value="lost">Lost</option></select>' +
        '<input type="number" name="stages[' + idx + '][stale_days]" placeholder="Stale days" class="crm-input">' +
        '</div>'
    );
};

CRM.submitPipelineForm = function(e) {
    e.preventDefault();
    var form = document.getElementById('create-pipeline-form');
    var stages = [];
    form.querySelectorAll('.stage-row').forEach(function(row) {
        var name = row.querySelector('[name*="[name]"]').value;
        var category = row.querySelector('[name*="[category]"]').value;
        var staleDays = row.querySelector('[name*="[stale_days]"]').value;
        if (name) stages.push({ name: name, category: category, stale_days: staleDays ? parseInt(staleDays) : null });
    });
    $.ajax({
        url: '{{ route("ajax.pipelines.store") }}',
        method: 'POST',
        data: JSON.stringify({
            name: form.querySelector('[name=name]').value,
            is_default: form.querySelector('[name=is_default]').checked,
            stages: stages
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.closeModal('create-pipeline-modal');
            form.reset();
            CRM.flash('Pipeline created.', 'success');
            CRM.loadPipelines();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error.', 'error'); }
    });
    return false;
};

CRM.openEditPipeline = function(id, name, isDefault) {
    document.getElementById('edit-pipeline-id').value = id;
    document.getElementById('edit-pipeline-name').value = name || '';
    document.getElementById('edit-pipeline-default').checked = !!isDefault;
    CRM.modal('edit-pipeline-modal');
};

CRM.submitEditPipelineForm = function(e) {
    e.preventDefault();
    var id = document.getElementById('edit-pipeline-id').value;
    $.ajax({
        url: '/ajax/pipelines/' + id,
        method: 'PATCH',
        data: JSON.stringify({
            name: document.getElementById('edit-pipeline-name').value,
            is_default: document.getElementById('edit-pipeline-default').checked
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.closeModal('edit-pipeline-modal');
            CRM.flash('Pipeline updated.', 'success');
            CRM.loadPipelines();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error.', 'error'); }
    });
    return false;
};

CRM.deletePipeline = function(id) {
    if (!confirm('Delete this pipeline?')) return;
    $.ajax({
        url: '/ajax/pipelines/' + id,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() { CRM.flash('Pipeline deleted.', 'success'); CRM.loadPipelines(); },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Cannot delete.', 'error'); }
    });
};

$(function() { CRM.loadPipelines(); });
</script>
@endpush
@endsection
