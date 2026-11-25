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
                                <td><span class="crm-badge crm-badge-{{ $org->pivot?->role ?? 'superadmin' }}">{{ $org->pivot?->role ?? 'superadmin' }}</span></td>
                                <td><span class="crm-badge crm-badge-{{ $org->status }}">{{ $org->status }}</span></td>
                                <td style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                    @if($activeOrganization && $activeOrganization->id === $org->id)
                                        <span class="crm-muted">Active</span>
                                    @elseif($org->isActive())
                                        <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm js-switch-org" data-org-id="{{ $org->id }}">Switch</button>
                                    @endif
                                    @if(auth()->user()?->isSuperadmin())
                                        <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" onclick="CRM.openEditOrg({{ $org->id }}, @js($org->name), @js($org->primary_contact_email ?? ''), @js($org->timezone), @js($org->currency), @js($org->status))">Edit</button>
                                        <form method="POST" action="{{ route('superadmin.organizations.destroy', $org) }}" onsubmit="return confirm('Delete this organization?')" style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>
                                        </form>
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

    @if(auth()->user()?->isSuperadmin())
        <div id="superadmin-edit-org-modal" class="crm-modal-backdrop">
            <div class="crm-modal">
                <div class="crm-modal-header">
                    <h2>Edit Organization</h2>
                    <button type="button" class="crm-modal-close" onclick="CRM.closeModal('superadmin-edit-org-modal')">&times;</button>
                </div>
                <form id="superadmin-edit-org-form" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <div class="crm-form-group">
                        <label class="crm-label">Name</label>
                        <input type="text" name="name" id="sa-org-name" class="crm-input" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Primary Contact Email</label>
                        <input type="email" name="primary_contact_email" id="sa-org-email" class="crm-input">
                    </div>
                    <div class="crm-grid crm-grid-2">
                        <div class="crm-form-group">
                            <label class="crm-label">Timezone</label>
                            <input type="text" name="timezone" id="sa-org-timezone" class="crm-input" required>
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Currency</label>
                            <input type="text" name="currency" id="sa-org-currency" class="crm-input" maxlength="3" required>
                        </div>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label">Status</label>
                        <select name="status" id="sa-org-status" class="crm-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="crm-modal-footer">
                        <button type="button" class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('superadmin-edit-org-modal')">Cancel</button>
                        <button type="submit" class="crm-btn crm-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            window.CRM = window.CRM || {};
            window.CRM.openEditOrg = function (id, name, email, timezone, currency, status) {
                document.getElementById('superadmin-edit-org-form').action = '/superadmin/organizations/' + id;
                document.getElementById('sa-org-name').value = name || '';
                document.getElementById('sa-org-email').value = email || '';
                document.getElementById('sa-org-timezone').value = timezone || 'UTC';
                document.getElementById('sa-org-currency').value = currency || 'INR';
                document.getElementById('sa-org-status').value = status || 'active';
                CRM.modal('superadmin-edit-org-modal');
            };
        </script>
    @endif

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
