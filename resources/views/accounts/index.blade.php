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

<!-- Edit Account Modal -->
<div id="edit-account-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>Edit Account</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('edit-account-modal')">&times;</button>
        </div>
        <form id="edit-account-form" onsubmit="return CRM.submitEditAccountForm(event)">
            <input type="hidden" id="edit-account-id">
            <div class="crm-form-group">
                <label for="edit-account-name">Account Name *</label>
                <input type="text" id="edit-account-name" name="name" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="edit-account-domain">Domain</label>
                <input type="text" id="edit-account-domain" name="domain" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="edit-account-industry">Industry</label>
                <input type="text" id="edit-account-industry" name="industry" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="edit-account-revenue">Annual Revenue</label>
                <input type="number" id="edit-account-revenue" name="annual_revenue" class="crm-input" step="0.01" min="0">
            </div>
            <div class="crm-form-group">
                <label for="edit-account-employees">Employee Count</label>
                <input type="number" id="edit-account-employees" name="employee_count" class="crm-input" min="1">
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('edit-account-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
CRM.openEditAccount = function(id, name, domain, industry, revenue, employees) {
    document.getElementById('edit-account-id').value = id;
    document.getElementById('edit-account-name').value = name || '';
    document.getElementById('edit-account-domain').value = domain || '';
    document.getElementById('edit-account-industry').value = industry || '';
    document.getElementById('edit-account-revenue').value = revenue || '';
    document.getElementById('edit-account-employees').value = employees || '';
    CRM.modal('edit-account-modal');
};

CRM.submitEditAccountForm = function(e) {
    e.preventDefault();
    var id = document.getElementById('edit-account-id').value;
    var form = document.getElementById('edit-account-form');
    $.ajax({
        url: '/ajax/accounts/' + id,
        method: 'PATCH',
        data: JSON.stringify({
            name: form.querySelector('[name=name]').value,
            domain: form.querySelector('[name=domain]').value || null,
            industry: form.querySelector('[name=industry]').value || null,
            annual_revenue: form.querySelector('[name=annual_revenue]').value || null,
            employee_count: form.querySelector('[name=employee_count]').value || null
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.closeModal('edit-account-modal');
            CRM.flash('Account updated.', 'success');
            if (typeof Livewire !== 'undefined') Livewire.dispatch('account-created');
            else location.reload();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error updating account.', 'error'); }
    });
    return false;
};

CRM.deleteAccount = function(id) {
    if (!confirm('Delete this account?')) return;
    $.ajax({
        url: '/ajax/accounts/' + id,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.flash('Account deleted.', 'success');
            if (typeof Livewire !== 'undefined') Livewire.dispatch('account-created');
            else location.reload();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error deleting account.', 'error'); }
    });
};
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
