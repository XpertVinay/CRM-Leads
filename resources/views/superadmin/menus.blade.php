@extends('layouts.crm')

@section('title', 'Portal Menus — Superadmin')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
        <div>
            <h1 class="crm-page-title" style="margin-bottom:0.25rem;">Portal Menus (Superadmin CRUD)</h1>
            <p class="crm-muted" style="margin:0;">Create, edit, reorder, toggle visibility, or remove sidebar navigation menus.</p>
        </div>
        <a href="{{ route('superadmin.rbac.index') }}" class="crm-btn crm-btn-secondary">Roles &amp; Permissions</a>
    </div>

    <div class="crm-card crm-mb-2">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Add Menu Item</h2>
        </div>
        <form method="POST" action="{{ route('superadmin.menus.store') }}">
            @csrf
            <div class="crm-grid crm-grid-3" style="gap:0.75rem;">
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_label">Label</label>
                    <input id="menu_label" class="crm-input" type="text" name="label" required placeholder="e.g. Leads">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_route_name">Route Name</label>
                    <input id="menu_route_name" class="crm-input" type="text" name="route_name" required placeholder="e.g. leads.index">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_route_pattern">Active Route Pattern</label>
                    <input id="menu_route_pattern" class="crm-input" type="text" name="route_pattern" placeholder="e.g. leads.*">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_icon">Material Icon</label>
                    <input id="menu_icon" class="crm-input" type="text" name="icon" required value="circle">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_permission">Required Permission</label>
                    <select id="menu_permission" class="crm-select" name="permission_slug">
                        <option value="">None (Public to authenticated)</option>
                        @foreach($permissions as $permission)
                            <option value="{{ $permission->slug }}">{{ $permission->module }} — {{ $permission->slug }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="menu_section">Section</label>
                    <select id="menu_section" class="crm-select" name="section">
                        <option value="main">Main</option>
                        <option value="pipeline">Pipeline &amp; Channels</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:1.25rem; flex-wrap:wrap; align-items:center; margin-top:0.5rem;">
                <div class="crm-form-group" style="margin:0; max-width:120px;">
                    <label class="crm-label" for="menu_sort">Sort Order</label>
                    <input id="menu_sort" class="crm-input" type="number" name="sort_order" value="100">
                </div>
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; margin-top:1rem;">
                    <input type="checkbox" name="superadmin_only" value="1">
                    <span>Superadmin Only</span>
                </label>
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; margin-top:1rem;">
                    <input type="checkbox" name="requires_org" value="1" checked>
                    <span>Requires Active Org</span>
                </label>
                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; margin-top:1rem;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active</span>
                </label>
                <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm" style="margin-top:1rem;">Create Menu</button>
            </div>
        </form>
    </div>

    <div class="crm-card">
        <div class="crm-card-header">
            <h2 class="crm-card-title">Configured Menus</h2>
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Label &amp; Icon</th>
                        <th>Route</th>
                        <th>Permission</th>
                        <th>Section</th>
                        <th>Flags &amp; Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($menus as $menu)
                        @php($updateFormId = 'menu-update-' . $menu->id)
                        <tr>
                            <td>
                                <input class="crm-input" type="text" name="label" form="{{ $updateFormId }}" value="{{ $menu->label }}" required style="margin-bottom:0.35rem;">
                                <input class="crm-input" type="text" name="icon" form="{{ $updateFormId }}" value="{{ $menu->icon }}" required placeholder="icon">
                            </td>
                            <td>
                                <input class="crm-input" type="text" name="route_name" form="{{ $updateFormId }}" value="{{ $menu->route_name }}" required style="margin-bottom:0.35rem;">
                                <input class="crm-input" type="text" name="route_pattern" form="{{ $updateFormId }}" value="{{ $menu->route_pattern }}">
                            </td>
                            <td>
                                <select class="crm-select" name="permission_slug" form="{{ $updateFormId }}">
                                    <option value="">None</option>
                                    @foreach($permissions as $permission)
                                        <option value="{{ $permission->slug }}" {{ $menu->permission_slug === $permission->slug ? 'selected' : '' }}>
                                            {{ $permission->slug }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="crm-select" name="section" form="{{ $updateFormId }}">
                                    <option value="main" {{ $menu->section === 'main' ? 'selected' : '' }}>main</option>
                                    <option value="pipeline" {{ $menu->section === 'pipeline' ? 'selected' : '' }}>pipeline</option>
                                    <option value="superadmin" {{ $menu->section === 'superadmin' ? 'selected' : '' }}>superadmin</option>
                                </select>
                            </td>
                            <td>
                                <input class="crm-input" type="number" name="sort_order" form="{{ $updateFormId }}" value="{{ $menu->sort_order }}" style="max-width:85px; margin-bottom:0.35rem;">
                                <div style="display:flex; flex-direction:column; gap:0.2rem; font-size:0.8rem;">
                                    <label><input type="checkbox" name="superadmin_only" form="{{ $updateFormId }}" value="1" {{ $menu->superadmin_only ? 'checked' : '' }}> Superadmin</label>
                                    <label><input type="checkbox" name="requires_org" form="{{ $updateFormId }}" value="1" {{ $menu->requires_org ? 'checked' : '' }}> Org Req</label>
                                    <label><input type="checkbox" name="is_active" form="{{ $updateFormId }}" value="1" {{ $menu->is_active ? 'checked' : '' }}> Active</label>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:0.35rem;">
                                    <form id="{{ $updateFormId }}" method="POST" action="{{ route('superadmin.menus.update', $menu) }}" style="margin:0;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('superadmin.menus.destroy', $menu) }}" style="margin:0;" onsubmit="return confirm('Delete menu {{ $menu->label }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="crm-btn crm-btn-danger crm-btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
