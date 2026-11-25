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
    <h3>Synced Templates ({{ $templates->count() }})</h3>
    <table class="crm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Language</th>
                <th>Category</th>
                <th>Status</th>
                <th>Body</th>
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
                </tr>
            @empty
                <tr><td colspan="5" class="crm-muted">No templates synced yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

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
</script>
@endpush
@endsection
