@extends('layouts.crm')

@section('title', 'Leads — CRM')

@section('content')
    <h1 class="crm-page-title">Dual-Mode Lead Inbox (B2B &amp; B2C)</h1>
    <p class="crm-muted crm-mb-2">Capture, assign, and convert B2B company leads or B2C individual consumer leads.</p>

    @can('create', \App\Modules\Leads\Models\Lead::class)
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Create Lead (jQuery AJAX)</h2>
            </div>
            <form id="js-create-lead-form">
                <div class="crm-grid crm-grid-3">
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_type">Lead Mode</label>
                        <select id="lead_type" class="crm-select" name="type">
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_first_name">First Name</label>
                        <input id="lead_first_name" class="crm-input" type="text" name="first_name" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_last_name">Last Name</label>
                        <input id="lead_last_name" class="crm-input" type="text" name="last_name">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_email">Email</label>
                        <input id="lead_email" class="crm-input" type="email" name="email">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_phone">Phone</label>
                        <input id="lead_phone" class="crm-input" type="text" name="phone">
                    </div>
                    <div class="crm-form-group js-b2b-only">
                        <label class="crm-label" for="lead_company">Company Name (B2B)</label>
                        <input id="lead_company" class="crm-input" type="text" name="company_name">
                    </div>
                    <div class="crm-form-group js-b2b-only">
                        <label class="crm-label" for="lead_job_title">Job Title (B2B)</label>
                        <input id="lead_job_title" class="crm-input" type="text" name="job_title">
                    </div>
                    <div class="crm-form-group js-b2b-only">
                        <label class="crm-label" for="lead_employee_count">Employee Count (B2B)</label>
                        <input id="lead_employee_count" class="crm-input" type="number" min="1" name="employee_count">
                    </div>
                    <div class="crm-form-group js-b2c-only">
                        <label class="crm-label" for="lead_location">Location (B2C)</label>
                        <input id="lead_location" class="crm-input" type="text" name="location">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="lead_source">Source</label>
                        <input id="lead_source" class="crm-input" type="text" name="source" value="web">
                    </div>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Add Lead</button>
            </form>
            <div id="js-lead-create-result" class="crm-mt-1"></div>
        </div>
    @endcan

    @livewire('leads.lead-inbox-table')
    @livewire('leads.lead-detail-modal')
    @livewire('leads.convert-lead-wizard')
@endsection

@push('scripts')
<script>
$(function () {
    function syncLeadModeFields() {
        var mode = $('#lead_type').val();
        if (mode === 'B2B') {
            $('.js-b2b-only').show();
            $('.js-b2c-only').hide();
        } else {
            $('.js-b2b-only').hide();
            $('.js-b2c-only').show();
        }
    }

    syncLeadModeFields();
    $('#lead_type').on('change', syncLeadModeFields);

    $('#js-create-lead-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/leads', $(this).serialize())
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-lead-create-result'), xhr); });
    });
});
</script>
@endpush
