@extends('layouts.crm')

@section('title', 'Organizations — CRM')

@section('content')
    <h1 class="crm-page-title">Organizations</h1>
    <p class="crm-muted crm-mb-2">Switch workspaces, create new tenant organizations, or configure active settings.</p>

    <div class="crm-grid crm-grid-2">
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Your Organizations</h2>
            </div>
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($organizations as $org)
                            <tr>
                                <td>
                                    <strong>{{ $org->name }}</strong>
                                    <div class="crm-muted">{{ $org->slug }}</div>
                                </td>
                                <td><span class="crm-badge crm-badge-{{ $org->pivot->role }}">{{ $org->pivot->role }}</span></td>
                                <td><span class="crm-badge crm-badge-{{ $org->status }}">{{ $org->status }}</span></td>
                                <td>
                                    @if($activeOrganization && $activeOrganization->id === $org->id)
                                        <span class="crm-muted">Active</span>
                                    @elseif($org->isActive())
                                        <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm js-switch-org" data-org-id="{{ $org->id }}">Switch</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Provision New Organization</h2>
            </div>
            <form method="POST" action="{{ route('organizations.store') }}">
                @csrf
                <div class="crm-form-group">
                    <label class="crm-label" for="org_name">Organization Name</label>
                    <input id="org_name" class="crm-input" type="text" name="name" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="org_reg_id">Registration Identifier (Optional)</label>
                    <input id="org_reg_id" class="crm-input" type="text" name="registration_identifier">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="org_email">Primary Contact Email (Optional)</label>
                    <input id="org_email" class="crm-input" type="email" name="primary_contact_email">
                </div>
                <div class="crm-grid crm-grid-2">
                    <div class="crm-form-group">
                        <label class="crm-label" for="org_timezone">Timezone</label>
                        <input id="org_timezone" class="crm-input" type="text" name="timezone" value="UTC">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="org_currency">Currency</label>
                        <input id="org_currency" class="crm-input" type="text" name="currency" value="USD" maxlength="3">
                    </div>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Create Organization</button>
            </form>
        </div>
    </div>

    @if($activeOrganization && $activeRole === 'org_admin')
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Active Organization Settings ({{ $activeOrganization->name }})</h2>
            </div>
            <form method="POST" action="{{ route('organizations.update') }}">
                @csrf
                @method('PATCH')
                <div class="crm-grid crm-grid-2">
                    <div class="crm-form-group">
                        <label class="crm-label" for="edit_org_name">Name</label>
                        <input id="edit_org_name" class="crm-input" type="text" name="name" value="{{ $activeOrganization->name }}" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="edit_org_email">Primary Contact Email</label>
                        <input id="edit_org_email" class="crm-input" type="email" name="primary_contact_email" value="{{ $activeOrganization->primary_contact_email }}">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="edit_org_timezone">Timezone</label>
                        <input id="edit_org_timezone" class="crm-input" type="text" name="timezone" value="{{ $activeOrganization->timezone }}" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="edit_org_currency">Currency</label>
                        <input id="edit_org_currency" class="crm-input" type="text" name="currency" value="{{ $activeOrganization->currency }}" maxlength="3" required>
                    </div>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Save Settings</button>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-switch-org').on('click', function () {
        var orgId = $(this).data('org-id');
        $.post('/ajax/organizations/switch', { organization_id: orgId })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors(null, xhr); });
    });
});
</script>
@endpush
