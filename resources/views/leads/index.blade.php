@extends('layouts.crm')

@section('title', 'Leads — CRM')

@section('content')
    <div class="crm-flex crm-mb-2" style="justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="crm-page-title">Dual-Mode Lead Inbox (B2B &amp; B2C)</h1>
            <p class="crm-muted" style="margin:0;">Capture, assign, and convert B2B company leads or B2C individual consumer leads.</p>
        </div>
        @can('create', \App\Modules\Leads\Models\Lead::class)
            <button type="button" id="js-open-create-lead-modal" class="crm-btn crm-btn-primary">
                + Create Lead
            </button>
        @endcan
    </div>

    @livewire('leads.lead-inbox-table')
    @livewire('leads.lead-detail-modal')
    @livewire('leads.convert-lead-wizard')

    @can('create', \App\Modules\Leads\Models\Lead::class)
        <div id="js-create-lead-modal" class="crm-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="js-create-lead-modal-title">
            <div class="crm-modal" style="max-width:780px;">
                <div class="crm-card-header">
                    <h2 id="js-create-lead-modal-title" class="crm-card-title">Create Lead</h2>
                    <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm js-close-create-lead-modal">Close</button>
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
                    <div id="js-lead-create-result" class="crm-mt-1"></div>
                    <div class="crm-flex crm-mt-1" style="justify-content:flex-end;gap:0.5rem;">
                        <button type="button" class="crm-btn crm-btn-secondary js-close-create-lead-modal">Cancel</button>
                        <button type="submit" class="crm-btn crm-btn-primary">Add Lead</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
$(function () {
    var $createModal = $('#js-create-lead-modal');

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

    function openCreateLeadModal() {
        $('#js-lead-create-result').empty();
        syncLeadModeFields();
        $createModal.addClass('open');
    }

    function closeCreateLeadModal() {
        $createModal.removeClass('open');
    }

    syncLeadModeFields();
    $('#lead_type').on('change', syncLeadModeFields);
    $('#js-open-create-lead-modal').on('click', openCreateLeadModal);
    $('.js-close-create-lead-modal').on('click', closeCreateLeadModal);
    $createModal.on('click', function (e) {
        if (e.target === this) {
            closeCreateLeadModal();
        }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $createModal.hasClass('open')) {
            closeCreateLeadModal();
        }
    });

    $('#js-create-lead-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/leads', $(this).serialize())
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-lead-create-result'), xhr); });
    });
});
</script>
@endpush
