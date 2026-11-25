@extends('layouts.crm')

@section('title', 'Organization Settings — CRM')

@section('content')
    <div class="crm-page-header">
        <div>
            <h1 class="crm-page-title">Organization Settings</h1>
            <p class="crm-muted">Configure WhatsApp redirect number, message templates, deduplication, and timezone.</p>
        </div>
    </div>

    <div class="crm-card">
        <div id="org-settings-errors"></div>
        <form id="org-settings-form">
            <div class="crm-form-row">
                <div class="crm-field">
                    <label>Organization Name</label>
                    <input name="name" value="{{ $organization->name }}" required>
                </div>
                <div class="crm-field">
                    <label>Timezone</label>
                    <input name="timezone" value="{{ $organization->timezone }}" required>
                </div>
            </div>
            <div class="crm-form-row">
                <div class="crm-field">
                    <label>Currency (ISO 3-letter)</label>
                    <input name="currency" value="{{ $organization->currency }}" maxlength="3" required>
                </div>
                <div class="crm-field">
                    <label>Lead Reference Prefix</label>
                    <input name="lead_reference_prefix" value="{{ $settings->lead_reference_prefix }}" maxlength="16" required>
                </div>
            </div>
            <div class="crm-form-row">
                <div class="crm-field">
                    <label>Default WhatsApp Business Phone (with country code)</label>
                    <input name="whatsapp_phone" value="{{ $settings->whatsapp_phone }}" placeholder="+919876543210">
                </div>
                <div class="crm-field">
                    <label>Duplicate Mobile Number Handling</label>
                    <select name="duplicate_lead_strategy">
                        @foreach($duplicateStrategies as $strategy)
                            <option value="{{ $strategy }}" @selected($settings->duplicate_lead_strategy === $strategy)>
                                {{ ucwords(str_replace('_', ' ', $strategy)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="crm-field">
                <label>Default WhatsApp Pre-filled Message</label>
                <textarea name="whatsapp_default_message" rows="3">{{ $settings->whatsapp_default_message }}</textarea>
                <small class="crm-muted">Available placeholders: {lead_ref}, {name}, {phone}, {company}, {campaign}</small>
            </div>
            <div class="crm-checkbox-group crm-mb-2">
                <label><input type="checkbox" name="capture_ip_address" value="1" @checked($settings->capture_ip_address)> Capture Visitor IP Address</label>
                <label><input type="checkbox" name="require_email" value="1" @checked($settings->require_email)> Require Email on Capture Form</label>
                <label><input type="checkbox" name="require_company" value="1" @checked($settings->require_company)> Require Company Name on Capture Form</label>
            </div>
            <button type="submit" class="crm-btn crm-btn-primary">Save Settings</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#org-settings-form').on('submit', function (e) {
        e.preventDefault();
        var payload = $(this).serializeArray();
        $.each(['capture_ip_address', 'require_email', 'require_company'], function (_i, name) {
            if (!$( '#org-settings-form [name="' + name + '"]').is(':checked')) {
                payload.push({ name: name, value: 0 });
            }
        });
        $.ajax({
            url: '/ajax/organizations/settings',
            method: 'PATCH',
            data: $.param(payload)
        }).done(function () {
            CrmUi.flash('Organization settings saved.');
        }).fail(function (xhr) {
            CrmUi.renderErrors($('#org-settings-errors'), xhr);
        });
    });
});
</script>
@endpush
