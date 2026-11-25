@extends('layouts.crm')

@section('title', 'Custom Fields — CRM')

@section('content')
    <h1 class="crm-page-title">Custom Field Definitions</h1>
    <p class="crm-muted crm-mb-2">Define dynamic JSON attributes for Leads, Contacts, Accounts, and Deals without schema migrations.</p>

    @livewire('custom-fields.custom-field-manager')

    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Quick AJAX Field Creator</h2>
        </div>
        <form id="js-custom-field-form">
            <div class="crm-grid crm-grid-3">
                <div class="crm-form-group">
                    <label class="crm-label" for="cf_entity">Entity</label>
                    <select id="cf_entity" class="crm-select" name="entity_type">
                        @foreach($entityTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="cf_key">Field Key</label>
                    <input id="cf_key" class="crm-input" type="text" name="field_key" placeholder="industry" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="cf_label">Label</label>
                    <input id="cf_label" class="crm-input" type="text" name="label" placeholder="Industry" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="cf_type">Data Type</label>
                    <select id="cf_type" class="crm-select" name="data_type">
                        @foreach($dataTypes as $dt)
                            <option value="{{ $dt }}">{{ $dt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="cf_options">Options (comma-separated for select)</label>
                    <input id="cf_options" class="crm-input" type="text" name="options_raw" placeholder="Fintech, Healthcare">
                </div>
            </div>
            <button type="submit" class="crm-btn crm-btn-secondary">Create via AJAX</button>
        </form>
        <div id="js-cf-result" class="crm-mt-1"></div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#js-custom-field-form').on('submit', function (e) {
        e.preventDefault();
        var dataType = $('#cf_type').val();
        var rawOpts = $('#cf_options').val() || '';
        var options = rawOpts.split(',').map(function (s) { return s.trim(); }).filter(Boolean);

        var payload = {
            entity_type: $('#cf_entity').val(),
            field_key: $('#cf_key').val(),
            label: $('#cf_label').val(),
            data_type: dataType
        };
        if (dataType === 'select' || dataType === 'multiselect') {
            payload.options = options;
        }

        $.post('/ajax/custom-fields', payload)
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-cf-result'), xhr); });
    });
});
</script>
@endpush
