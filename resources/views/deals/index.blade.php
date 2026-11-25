@extends('layouts.crm')

@section('title', 'Deals')

@section('content')
<div class="crm-page-header">
    <h1>Deals</h1>
    <button class="crm-btn crm-btn-primary" onclick="CRM.modal('create-deal-modal')">+ New Deal</button>
</div>

@livewire('pipeline.deal-kanban-board')

<!-- Create Deal Modal -->
<div id="create-deal-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>New Deal</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('create-deal-modal')">&times;</button>
        </div>
        <form id="create-deal-form" onsubmit="return CRM.submitDealForm(event)">
            <div class="crm-form-group">
                <label for="deal-title">Title *</label>
                <input type="text" id="deal-title" name="title" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="deal-contact">Contact ID *</label>
                <input type="number" id="deal-contact" name="contact_id" required class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="deal-account">Account ID (optional)</label>
                <input type="number" id="deal-account" name="account_id" class="crm-input">
            </div>
            <div class="crm-form-row">
                <div class="crm-form-group">
                    <label for="deal-amount">Amount</label>
                    <input type="number" id="deal-amount" name="amount" class="crm-input" step="0.01" min="0" value="0">
                </div>
                <div class="crm-form-group">
                    <label for="deal-currency">Currency</label>
                    <input type="text" id="deal-currency" name="currency" class="crm-input" value="USD" maxlength="3">
                </div>
            </div>
            <div class="crm-form-group">
                <label for="deal-close-date">Expected Close Date</label>
                <input type="date" id="deal-close-date" name="expected_close_date" class="crm-input">
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('create-deal-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Create Deal</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
CRM.submitDealForm = function(e) {
    e.preventDefault();
    var form = document.getElementById('create-deal-form');
    var data = {
        title: form.querySelector('[name=title]').value,
        contact_id: parseInt(form.querySelector('[name=contact_id]').value),
        account_id: form.querySelector('[name=account_id]').value || null,
        amount: parseFloat(form.querySelector('[name=amount]').value) || 0,
        currency: form.querySelector('[name=currency]').value || 'USD',
        expected_close_date: form.querySelector('[name=expected_close_date]').value || null
    };
    $.ajax({
        url: '{{ route("ajax.deals.store") }}',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.closeModal('create-deal-modal');
            form.reset();
            CRM.flash('Deal created.', 'success');
            if (typeof Livewire !== 'undefined') Livewire.dispatch('deal-created');
            else location.reload();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error.', 'error'); }
    });
    return false;
};
</script>
@endpush
@endsection
