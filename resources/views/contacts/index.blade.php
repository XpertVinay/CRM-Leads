@extends('layouts.crm')

@section('title', 'Contacts')

@section('content')
<div class="crm-page-header">
    <h1>Contacts</h1>
    <button class="crm-btn crm-btn-primary" onclick="CRM.modal('create-contact-modal')">+ New Contact</button>
</div>

<div class="crm-card">
    <div class="crm-toolbar">
        <input type="text" id="contact-search" class="crm-input" placeholder="Search contacts..." style="max-width:300px;">
    </div>
    <table class="crm-table" id="contacts-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Account</th>
                <th>Preferred Channel</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody id="contacts-tbody"></tbody>
    </table>
</div>

<!-- Create Contact Modal -->
<div id="create-contact-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2>New Contact</h2>
            <button class="crm-modal-close" onclick="CRM.closeModal('create-contact-modal')">&times;</button>
        </div>
        <form id="create-contact-form" onsubmit="return CRM.submitContactForm(event)">
            <div class="crm-form-row">
                <div class="crm-form-group">
                    <label for="contact-first-name">First Name *</label>
                    <input type="text" id="contact-first-name" name="first_name" required class="crm-input">
                </div>
                <div class="crm-form-group">
                    <label for="contact-last-name">Last Name</label>
                    <input type="text" id="contact-last-name" name="last_name" class="crm-input">
                </div>
            </div>
            <div class="crm-form-group">
                <label for="contact-email">Email</label>
                <input type="email" id="contact-email" name="email" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="contact-phone">Phone</label>
                <input type="text" id="contact-phone" name="phone" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="contact-account">Account ID (optional)</label>
                <input type="number" id="contact-account" name="account_id" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="contact-job-title">Job Title</label>
                <input type="text" id="contact-job-title" name="job_title" class="crm-input">
            </div>
            <div class="crm-form-group">
                <label for="contact-channel">Preferred Channel</label>
                <select id="contact-channel" name="preferred_channel" class="crm-select">
                    <option value="email">Email</option>
                    <option value="phone">Phone</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('create-contact-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Create Contact</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
CRM.loadContacts = function(search) {
    $.get('{{ route("ajax.contacts.index") }}', { search: search || '' }, function(resp) {
        var tbody = $('#contacts-tbody');
        tbody.empty();
        (resp.data || []).forEach(function(c) {
            tbody.append(
                '<tr>' +
                '<td><a href="/contacts/' + c.id + '">' + (c.first_name + ' ' + (c.last_name || '')).trim() + '</a></td>' +
                '<td>' + (c.email || '—') + '</td>' +
                '<td>' + (c.phone || '—') + '</td>' +
                '<td>' + (c.account ? c.account.name : '—') + '</td>' +
                '<td>' + (c.preferred_channel || 'email') + '</td>' +
                '<td>' + (c.created_at ? new Date(c.created_at).toLocaleDateString() : '') + '</td>' +
                '</tr>'
            );
        });
    });
};

CRM.submitContactForm = function(e) {
    e.preventDefault();
    var form = document.getElementById('create-contact-form');
    var data = {
        first_name: form.querySelector('[name=first_name]').value,
        last_name: form.querySelector('[name=last_name]').value || null,
        email: form.querySelector('[name=email]').value || null,
        phone: form.querySelector('[name=phone]').value || null,
        account_id: form.querySelector('[name=account_id]').value || null,
        job_title: form.querySelector('[name=job_title]').value || null,
        preferred_channel: form.querySelector('[name=preferred_channel]').value
    };
    $.ajax({
        url: '{{ route("ajax.contacts.store") }}',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() {
            CRM.closeModal('create-contact-modal');
            form.reset();
            CRM.flash('Contact created.', 'success');
            CRM.loadContacts();
        },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error.', 'error'); }
    });
    return false;
};

$(function() {
    CRM.loadContacts();
    var debounce;
    $('#contact-search').on('input', function() {
        clearTimeout(debounce);
        var val = $(this).val();
        debounce = setTimeout(function() { CRM.loadContacts(val); }, 300);
    });
});
</script>
@endpush
@endsection
