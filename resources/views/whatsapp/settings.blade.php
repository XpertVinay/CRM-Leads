@extends('layouts.crm')

@section('title', 'WhatsApp Cloud API Settings')

@section('content')
<div class="crm-page-header">
    <h1>WhatsApp Cloud API Settings</h1>
</div>

<div class="crm-grid crm-grid-2">
    <div class="crm-card">
        <h3>Connection Status</h3>
        @if($account && $account->is_active)
            <p><span class="crm-badge crm-badge-active">Connected</span></p>
            <dl class="crm-dl">
                <dt>WABA ID</dt><dd>{{ $account->waba_id }}</dd>
                <dt>Phone Number ID</dt><dd>{{ $account->phone_number_id }}</dd>
                <dt>Display Phone</dt><dd>{{ $account->display_phone_number }}</dd>
                <dt>Verified Name</dt><dd>{{ $account->verified_name ?? '—' }}</dd>
                <dt>Coexistence</dt><dd>{{ $account->is_coexistence_enabled ? 'Enabled' : 'Disabled' }}</dd>
            </dl>
            <div style="margin-top:1rem;">
                <button class="crm-btn crm-btn-secondary crm-btn-sm" onclick="CRM.syncTemplates()">Sync Templates</button>
                <button class="crm-btn crm-btn-danger crm-btn-sm" onclick="CRM.disconnectWa()">Disconnect</button>
            </div>
        @else
            <p class="crm-muted">WhatsApp is not connected. Connect your Meta Cloud API account below (optional).</p>
        @endif
    </div>

    <div class="crm-card">
        <h3>Connect Meta WABA</h3>
        <form id="wa-connect-form" onsubmit="return CRM.connectWa(event)">
            <div class="crm-form-group">
                <label class="crm-label">WABA ID *</label>
                <input type="text" name="waba_id" required class="crm-input" value="{{ $account->waba_id ?? '' }}">
            </div>
            <div class="crm-form-group">
                <label class="crm-label">Phone Number ID *</label>
                <input type="text" name="phone_number_id" required class="crm-input" value="{{ $account->phone_number_id ?? '' }}">
            </div>
            <div class="crm-form-group">
                <label class="crm-label">Display Phone Number</label>
                <input type="text" name="display_phone_number" class="crm-input" placeholder="+15551234567" value="{{ $account->display_phone_number ?? '' }}">
            </div>
            <div class="crm-form-group">
                <label class="crm-label">System User Access Token</label>
                <input type="password" name="access_token" class="crm-input" placeholder="EAAG...">
            </div>
            <div class="crm-form-group">
                <label><input type="checkbox" name="is_coexistence_enabled" value="1" {{ ($account->is_coexistence_enabled ?? false) ? 'checked' : '' }}> Enable Meta Coexistence Mode</label>
            </div>
            <button type="submit" class="crm-btn crm-btn-primary">Save &amp; Connect</button>
        </form>
    </div>
</div>

<div class="crm-card" style="margin-top:1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
        <h3 style="margin:0;">Synced Templates ({{ $templates->count() }})</h3>
        @if(auth()->user()?->isSuperadmin() && $account)
            <button type="button" class="crm-btn crm-btn-primary crm-btn-sm" onclick="CRM.openWaTemplateModal()">+ New Template</button>
        @endif
    </div>
    <table class="crm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Language</th>
                <th>Category</th>
                <th>Status</th>
                <th>Body</th>
                @if(auth()->user()?->isSuperadmin())
                    <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $tpl)
                <tr>
                    <td>{{ $tpl->name }}</td>
                    <td>{{ $tpl->language }}</td>
                    <td>{{ $tpl->category }}</td>
                    <td><span class="crm-badge">{{ $tpl->meta_status }}</span></td>
                    <td>{{ $tpl->body_text }}</td>
                    @if(auth()->user()?->isSuperadmin())
                        <td>
                            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" onclick="CRM.openWaTemplateModal({{ $tpl->id }}, @js($tpl->name), @js($tpl->language), @js($tpl->category), @js($tpl->meta_status), @js($tpl->body_text))">Edit</button>
                            <button type="button" class="crm-btn crm-btn-danger crm-btn-sm" onclick="CRM.deleteWaTemplate({{ $tpl->id }})">Delete</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()?->isSuperadmin() ? 6 : 5 }}" class="crm-muted">No templates synced yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(auth()->user()?->isSuperadmin())
<div id="wa-template-modal" class="crm-modal-backdrop">
    <div class="crm-modal">
        <div class="crm-modal-header">
            <h2 id="wa-tpl-modal-title">New WhatsApp Template</h2>
            <button type="button" class="crm-modal-close" onclick="CRM.closeModal('wa-template-modal')">&times;</button>
        </div>
        <form id="wa-template-form" onsubmit="return CRM.submitWaTemplate(event)">
            <input type="hidden" id="wa-tpl-id">
            <div class="crm-form-group">
                <label class="crm-label">Template Name *</label>
                <input type="text" id="wa-tpl-name" required class="crm-input" placeholder="welcome_message">
            </div>
            <div class="crm-form-row">
                <div class="crm-form-group">
                    <label class="crm-label">Language *</label>
                    <input type="text" id="wa-tpl-lang" required class="crm-input" value="en_IN">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label">Category *</label>
                    <select id="wa-tpl-category" class="crm-select">
                        <option value="utility">Utility</option>
                        <option value="marketing">Marketing</option>
                        <option value="authentication">Authentication</option>
                    </select>
                </div>
            </div>
            <div class="crm-form-group">
                <label class="crm-label">Status</label>
                <select id="wa-tpl-status" class="crm-select">
                    <option value="APPROVED">APPROVED</option>
                    <option value="PENDING">PENDING</option>
                    <option value="REJECTED">REJECTED</option>
                </select>
            </div>
            <div class="crm-form-group">
                <label class="crm-label">Body Text *</label>
                <textarea id="wa-tpl-body" rows="3" required class="crm-input"></textarea>
            </div>
            <div class="crm-modal-footer">
                <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('wa-template-modal')">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
CRM.connectWa = function(e) {
    e.preventDefault();
    var form = document.getElementById('wa-connect-form');
    $.ajax({
        url: '{{ route("ajax.whatsapp.connect") }}',
        method: 'POST',
        data: JSON.stringify({
            waba_id: form.querySelector('[name=waba_id]').value,
            phone_number_id: form.querySelector('[name=phone_number_id]').value,
            display_phone_number: form.querySelector('[name=display_phone_number]').value || null,
            access_token: form.querySelector('[name=access_token]').value || null,
            is_coexistence_enabled: form.querySelector('[name=is_coexistence_enabled]').checked
        }),
        contentType: 'application/json',
        success: function() { location.reload(); },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Connection failed.', 'error'); }
    });
    return false;
};
CRM.syncTemplates = function() {
    $.post('{{ route("ajax.whatsapp.templates.sync") }}', function() { location.reload(); })
        .fail(function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Sync failed.', 'error'); });
};
CRM.disconnectWa = function() {
    $.ajax({
        url: '{{ route("ajax.whatsapp.disconnect") }}',
        method: 'DELETE',
        success: function() { location.reload(); }
    });
};
CRM.openWaTemplateModal = function(id, name, lang, category, status, body) {
    document.getElementById('wa-tpl-id').value = id || '';
    document.getElementById('wa-tpl-modal-title').textContent = id ? 'Edit WhatsApp Template' : 'New WhatsApp Template';
    document.getElementById('wa-tpl-name').value = name || '';
    document.getElementById('wa-tpl-lang').value = lang || 'en_IN';
    document.getElementById('wa-tpl-category').value = category || 'utility';
    document.getElementById('wa-tpl-status').value = status || 'APPROVED';
    document.getElementById('wa-tpl-body').value = body || '';
    CRM.modal('wa-template-modal');
};
CRM.submitWaTemplate = function(e) {
    e.preventDefault();
    var id = document.getElementById('wa-tpl-id').value;
    $.ajax({
        url: id ? '/ajax/whatsapp/templates/' + id : '{{ route("ajax.whatsapp.templates.store") }}',
        method: id ? 'PATCH' : 'POST',
        data: JSON.stringify({
            name: document.getElementById('wa-tpl-name').value,
            language: document.getElementById('wa-tpl-lang').value,
            category: document.getElementById('wa-tpl-category').value,
            meta_status: document.getElementById('wa-tpl-status').value,
            body_text: document.getElementById('wa-tpl-body').value
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() { location.reload(); },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error saving template.', 'error'); }
    });
    return false;
};
CRM.deleteWaTemplate = function(id) {
    if (!confirm('Delete this template?')) return;
    $.ajax({
        url: '/ajax/whatsapp/templates/' + id,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
        success: function() { location.reload(); },
        error: function(xhr) { CRM.flash(xhr.responseJSON?.message || 'Error deleting template.', 'error'); }
    });
};
</script>
@endpush
@endsection
