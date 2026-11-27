@extends('layouts.crm')

@section('title', 'Team & API Tokens — CRM')

@section('content')
    <h1 class="crm-page-title">Team, Invitations &amp; API Tokens</h1>
    <p class="crm-muted crm-mb-2">Manage organization roles, pending invitations, and hashed API access tokens.</p>

    <div class="crm-grid crm-grid-2">
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Invite Team Member</h2>
            </div>
            <form id="js-invite-form">
                <div class="crm-form-group">
                    <label class="crm-label" for="invite_email">Email</label>
                    <input id="invite_email" class="crm-input" type="email" name="email" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="invite_role">Role</label>
                    <select id="invite_role" class="crm-select" name="role">
                        @foreach($roles as $role)
                            <option value="{{ $role->slug }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Send Invitation</button>
            </form>
            <div id="js-invite-result" class="crm-mt-1"></div>
        </div>

        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Issue API Token</h2>
            </div>
            <form id="js-token-form">
                <div class="crm-form-group">
                    <label class="crm-label" for="token_name">Token Name</label>
                    <input id="token_name" class="crm-input" type="text" name="name" placeholder="Zapier Integration" required>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary">Generate Token</button>
            </form>
            <div id="js-token-result" class="crm-mt-1"></div>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Organization Members</h2>
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        @php
                            $canUpdateMemberRole = auth()->user()?->isSuperadmin()
                                || ((int) ($member->pivot->invited_by_user_id ?? 0) === (int) auth()->id()
                                    && $member->pivot->role !== \App\Modules\Tenancy\Enums\TenantRole::OrgAdmin->value);
                        @endphp
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @if($canUpdateMemberRole)
                                    <select class="crm-select js-member-role" data-user-id="{{ $member->id }}" style="max-width: 180px;">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->slug }}" {{ $member->pivot->role === $role->slug ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="crm-badge crm-badge-{{ $member->pivot->role }}">{{ $member->pivot->role }}</span>
                                    <div class="crm-muted" style="font-size:0.75rem;">Managed by inviter / Superadmin</div>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="crm-btn crm-btn-danger crm-btn-sm js-remove-member" data-user-id="{{ $member->id }}">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Active API Tokens</h2>
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Prefix</th>
                        <th>Owner</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td><code>{{ $token->token_prefix }}...</code></td>
                            <td>{{ $token->user?->email }}</td>
                            <td>
                                <button type="button" class="crm-btn crm-btn-danger crm-btn-sm js-revoke-token" data-token-id="{{ $token->id }}">Revoke</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="crm-muted">No active API tokens.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#js-invite-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/organizations/invite', $(this).serialize())
            .done(function (res) {
                $('#js-invite-result').html('<div class="crm-alert crm-alert-success">Invite link: <code>' + CRM.escapeHtml(res.accept_url) + '</code></div>');
            })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-invite-result'), xhr); });
    });

    $('#js-token-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/team/tokens', $(this).serialize())
            .done(function (res) {
                $('#js-token-result').html('<div class="crm-alert crm-alert-success">Token (copy now): <code>' + CRM.escapeHtml(res.plain_text_token) + '</code></div>');
            })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-token-result'), xhr); });
    });

    $('.js-member-role').on('change', function () {
        var userId = $(this).data('user-id');
        var role = $(this).val();
        $.ajax({ url: '/ajax/team/members/' + userId + '/role', method: 'PATCH', data: { role: role } })
            .done(function () { window.CrmUi.flash('Member role updated.'); })
            .fail(function (xhr) { window.CrmUi.renderErrors(null, xhr); });
    });

    $('.js-remove-member').on('click', function () {
        var userId = $(this).data('user-id');
        $.ajax({ url: '/ajax/team/members/' + userId, method: 'DELETE' })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors(null, xhr); });
    });

    $('.js-revoke-token').on('click', function () {
        var tokenId = $(this).data('token-id');
        $.ajax({ url: '/ajax/team/tokens/' + tokenId, method: 'DELETE' })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors(null, xhr); });
    });
});
</script>
@endpush
