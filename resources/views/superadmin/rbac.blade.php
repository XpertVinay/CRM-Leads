@extends('layouts.crm')

@section('title', 'Roles & Permissions — Superadmin')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
        <div>
            <h1 class="crm-page-title" style="margin-bottom:0.25rem;">Roles &amp; Permissions (RBAC)</h1>
            <p class="crm-muted" style="margin:0;">Superadmin CRUD for roles, permissions, role permission matrix, and per-user overrides.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <a href="{{ route('superadmin.menus.index') }}" class="crm-btn crm-btn-secondary">Manage Portal Menus</a>
            <form method="POST" action="{{ route('superadmin.rbac.cache.flush') }}" style="margin:0;">
                @csrf
                <button type="submit" class="crm-btn crm-btn-secondary">Flush RBAC Cache</button>
            </form>
        </div>
    </div>

    <div class="crm-grid crm-grid-2 crm-mb-2" style="gap:1.25rem;">
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Create Role</h2>
            </div>
            <form method="POST" action="{{ route('superadmin.rbac.roles.store') }}">
                @csrf
                <div class="crm-form-group">
                    <label class="crm-label" for="role_name">Role Name</label>
                    <input id="role_name" class="crm-input" type="text" name="name" placeholder="e.g. Support Agent" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="role_slug">Slug (optional)</label>
                    <input id="role_slug" class="crm-input" type="text" name="slug" placeholder="e.g. support_agent">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="role_description">Description</label>
                    <input id="role_description" class="crm-input" type="text" name="description" placeholder="Role scope">
                </div>
                <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm">Create Role</button>
            </form>
        </div>

        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Create Permission</h2>
            </div>
            <form method="POST" action="{{ route('superadmin.rbac.permissions.store') }}">
                @csrf
                <div class="crm-grid crm-grid-2" style="gap:0.75rem;">
                    <div class="crm-form-group">
                        <label class="crm-label" for="perm_slug">Slug</label>
                        <input id="perm_slug" class="crm-input" type="text" name="slug" placeholder="e.g. invoices.view" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="perm_module">Module</label>
                        <input id="perm_module" class="crm-input" type="text" name="module" placeholder="e.g. Billing" required>
                    </div>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="perm_name">Permission Name</label>
                    <input id="perm_name" class="crm-input" type="text" name="name" placeholder="e.g. View Invoices" required>
                </div>
                <div class="crm-grid crm-grid-2" style="gap:0.75rem; align-items:center;">
                    <div class="crm-form-group">
                        <label class="crm-label" for="perm_sort">Sort Order</label>
                        <input id="perm_sort" class="crm-input" type="number" name="sort_order" value="200">
                    </div>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; margin-top:0.75rem;">
                        <input type="checkbox" name="is_menu_item" value="1">
                        <span>Menu Permission</span>
                    </label>
                </div>
                <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm">Create Permission</button>
            </form>
        </div>
    </div>

    <div class="crm-card crm-mb-2">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Role Permission Matrix</h2>
        </div>
        <div class="crm-grid crm-grid-2" style="gap:1.25rem;">
            @foreach($roles as $role)
                @php
                    $assignedIds = $role->permissions->pluck('id')->all();
                @endphp
                <div class="crm-card" style="border:1px solid var(--crm-border, #e2e8f0);">
                    <div class="crm-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 class="crm-card-title" style="margin:0;">{{ $role->name }}</h3>
                            <div class="crm-muted" style="font-size:0.8rem;">{{ $role->slug }} @if($role->is_system) · System @endif</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span class="crm-badge crm-badge-{{ $role->slug }}">{{ count($assignedIds) }} permissions</span>
                            @if(! $role->is_system)
                                <form method="POST" action="{{ route('superadmin.rbac.roles.destroy', $role) }}" style="margin:0;" onsubmit="return confirm('Delete role {{ $role->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.rbac.roles.update', $role) }}">
                        @csrf
                        <div style="max-height:360px; overflow-y:auto; padding-right:0.5rem;">
                            @foreach($permissionsByModule as $module => $permissions)
                                <div style="margin-bottom:0.85rem;">
                                    <div style="font-weight:600; font-size:0.85rem; margin-bottom:0.35rem; color:var(--crm-primary, #2563eb);">{{ $module }}</div>
                                    @foreach($permissions as $permission)
                                        <label style="display:flex; align-items:center; gap:0.45rem; font-size:0.875rem; margin-bottom:0.25rem; cursor:pointer;">
                                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}"
                                                {{ in_array($permission->id, $assignedIds, true) ? 'checked' : '' }}>
                                            <span>{{ $permission->name }}</span>
                                            <span class="crm-muted" style="font-size:0.75rem;">({{ $permission->slug }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <div style="margin-top:0.75rem; padding-top:0.75rem; border-top:1px solid var(--crm-border, #e2e8f0);">
                            <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm">Save {{ $role->name }}</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <div class="crm-card crm-mb-2">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Permissions Catalog</h2>
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissionsByModule as $module => $permissions)
                        @foreach($permissions as $permission)
                            <tr>
                                <td>{{ $module }}</td>
                                <td>{{ $permission->name }}</td>
                                <td><code>{{ $permission->slug }}</code></td>
                                <td>
                                    <form method="POST" action="{{ route('superadmin.rbac.permissions.destroy', $permission) }}" style="margin:0;" onsubmit="return confirm('Delete permission {{ $permission->slug }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">User-Specific Permission Overrides</h2>
        </div>
        <form method="GET" action="{{ route('superadmin.rbac.index') }}" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:1rem;">
            <div class="crm-form-group" style="margin:0; min-width:220px;">
                <label class="crm-label" for="rbac-org-select">Organization</label>
                <select id="rbac-org-select" name="organization_id" class="crm-input" onchange="this.form.submit()">
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ $selectedOrg && $selectedOrg->id === $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="crm-form-group" style="margin:0; min-width:220px;">
                <label class="crm-label" for="rbac-user-select">Employee / User</label>
                <select id="rbac-user-select" name="user_id" class="crm-input" onchange="this.form.submit()">
                    @forelse($orgUsers as $orgUser)
                        <option value="{{ $orgUser->id }}" {{ $selectedUserId === $orgUser->id ? 'selected' : '' }}>
                            {{ $orgUser->name }} ({{ $orgUser->pivot?->role }})
                        </option>
                    @empty
                        <option value="">No users in organization</option>
                    @endforelse
                </select>
            </div>
        </form>

        @if($selectedOrg && $selectedUserId > 0)
            <form method="POST" action="{{ route('superadmin.rbac.user-overrides') }}">
                @csrf
                <input type="hidden" name="organization_id" value="{{ $selectedOrg->id }}">
                <input type="hidden" name="user_id" value="{{ $selectedUserId }}">
                <div class="crm-table-wrap">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Permission</th>
                                <th>Override</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissionsByModule as $module => $permissions)
                                @foreach($permissions as $permission)
                                    @php
                                        $current = array_key_exists($permission->id, $userOverrides)
                                            ? ($userOverrides[$permission->id] ? 'allow' : 'deny')
                                            : 'inherit';
                                    @endphp
                                    <tr>
                                        <td>{{ $module }}</td>
                                        <td>
                                            <strong>{{ $permission->name }}</strong>
                                            <div class="crm-muted" style="font-size:0.75rem;">{{ $permission->slug }}</div>
                                        </td>
                                        <td>
                                            <select name="overrides[{{ $permission->id }}]" class="crm-input" style="max-width:160px;">
                                                <option value="inherit" {{ $current === 'inherit' ? 'selected' : '' }}>Inherit from Role</option>
                                                <option value="allow" {{ $current === 'allow' ? 'selected' : '' }}>Allow</option>
                                                <option value="deny" {{ $current === 'deny' ? 'selected' : '' }}>Deny</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:1rem;">
                    <button type="submit" class="crm-btn crm-btn-primary">Save User Overrides</button>
                </div>
            </form>
        @endif
    </div>
@endsection
