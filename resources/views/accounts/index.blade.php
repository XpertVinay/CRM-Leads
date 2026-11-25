@extends('layouts.crm')

@section('title', 'Accounts')

@section('content')
<div class="crm-page-header">
    <h1>Accounts</h1>
    <button class="crm-btn crm-btn-primary" onclick="CRM.modal('create-account-modal')">+ New Account</button>
</div>

@livewire('pipeline.account-list')

<!-- Create Account Modal -->
<div id="create-account-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>New Account</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('create-account-modal')">&times;</button>
        </div>
        <form id="create-account-form" onsubmit="return CRM.submitAccountForm(event)">
            <div class="crm-form-group">
                <label for="account-name">Account Name *</label>
                <input type="text" id="account-name" name="name" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="account-domain">Domain</label>
                <input type="text" id="account-domain" name="domain" class="crm-input" placeholder="example.com">
            </div>
            <div class="crm-form-group">
                <label for="account-industry">Industry</label>
                <input type="text" id="account-industry" name="industry" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="account-revenue">Annual Revenue</label>
                <input type="number" id="account-revenue" name="annual_revenue" class="crm-input" step="0.01" min="0">
            </div>
            <div class="crm-form-group">
                <label for="account-employees">Employee Count</label>
                <input type="number" id="account-employees" name="employee_count" class="crm-input" min="1">
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('create-account-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
CRM.submitAccountForm = function(e) {
    e.preventDefault();
    var form = document.getElementById('create-account-form');
    var data = {
        name: form.querySelector('[name=name]').value,
        domain: form.querySelector('[name=domain]').value || null,
        industry: form.querySelector('[name=industry]').value || null,
        annual_revenue: form.querySelector('[name=annual_revenue]').value || null,
        employee_count: form.querySelector('[name=employee_count]').value || null
    };
    $.ajax({
        url: '{{ route("ajax.accounts.store") }}',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function(resp) {
            CRM.closeModal('create-account-modal');
            form.reset();
            CRM.flash('Account created successfully.', 'success');
            if (typeof Livewire !== 'undefined') Livewire.dispatch('account-created');
            else location.reload();
        },
        error: function(xhr) {
            CRM.flash(xhr.responseJSON?.message || 'Error creating account.', 'error');
        }
    });
    return false;
};
</script>
@endpush
@endsection
