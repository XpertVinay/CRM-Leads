<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'CRM'))</title>
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
    @livewireStyles
</head>
<body>
@auth
    <div class="crm-shell">
        <aside class="crm-sidebar">
            <div class="crm-brand">{{ config('app.name', 'Modular CRM') }}</div>
            <nav class="crm-nav">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                @if(isset($activeOrganization))
                    <a href="{{ route('leads.index') }}" class="{{ request()->routeIs('leads.*') ? 'active' : '' }}">Leads</a>
                    <a href="{{ route('tracking-links.index') }}" class="{{ request()->routeIs('tracking-links.*') ? 'active' : '' }}">Tracking Links</a>
                @endif
                <a href="{{ route('organizations.index') }}" class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">Organizations</a>
                @if(isset($activeOrganization) && in_array($activeRole ?? null, ['org_admin', 'superadmin'], true))
                    <a href="{{ route('settings.team') }}" class="{{ request()->routeIs('settings.team') ? 'active' : '' }}">Team &amp; API Tokens</a>
                    <a href="{{ route('settings.custom-fields') }}" class="{{ request()->routeIs('settings.custom-fields') ? 'active' : '' }}">Custom Fields</a>
                @endif
            </nav>
        </aside>

        <div class="crm-main">
            <header class="crm-topbar">
                <div>
                    @if(isset($activeOrganization))
                        <strong>{{ $activeOrganization->name }}</strong>
                        @if(isset($activeRole))
                            <span class="crm-badge crm-badge-{{ $activeRole }}">{{ $activeRole }}</span>
                        @endif
                    @else
                        <span class="crm-muted">No active organization selected</span>
                    @endif
                </div>

                <div class="crm-topbar-right">
                    @livewire('tenancy.organization-switcher')
                    <span class="crm-muted">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="crm-btn crm-btn-secondary crm-btn-sm">Sign Out</button>
                    </form>
                </div>
            </header>

            <main class="crm-content">
                <div id="crm-flash-area">
                    @if(session('status'))
                        <div class="crm-alert crm-alert-success">{{ session('status') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="crm-alert crm-alert-error">{{ $errors->first() }}</div>
                    @endif
                </div>

                @yield('content')
            </main>
        </div>
    </div>
@else
    <div class="crm-auth-wrapper">
        <div class="crm-auth-card">
            @if(session('status'))
                <div class="crm-alert crm-alert-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="crm-alert crm-alert-error">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </div>
@endauth

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/crm.js') }}"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
