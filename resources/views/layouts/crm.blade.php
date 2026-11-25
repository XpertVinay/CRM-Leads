<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'CRM'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
    @livewireStyles
</head>
<body>
@auth
    <div class="crm-shell">
        <div id="crm-sidebar-backdrop" class="crm-sidebar-backdrop"></div>
        <aside id="crm-sidebar" class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-icon">
                    <span class="material-symbols-outlined" style="font-size:20px;">token</span>
                </span>
                <span>{{ config('app.name', 'Modular CRM') }}</span>
            </div>
            <nav class="crm-nav">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                @if(isset($activeOrganization))
                    <a href="{{ route('leads.index') }}" class="{{ request()->routeIs('leads.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">inbox</span>
                        <span>Leads</span>
                    </a>
                    <a href="{{ route('tracking-links.index') }}" class="{{ request()->routeIs('tracking-links.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">link</span>
                        <span>Tracking Links</span>
                    </a>
                    <a href="{{ route('campaigns.index') }}" class="{{ request()->routeIs('campaigns.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">campaign</span>
                        <span>Campaigns</span>
                    </a>
                    @if(in_array($activeRole ?? null, ['org_admin', 'superadmin'], true))
                        <a href="{{ route('settings.team') }}" class="{{ request()->routeIs('settings.team') ? 'active' : '' }}">
                            <span class="material-symbols-outlined">group</span>
                            <span>Employees</span>
                        </a>
                    @endif
                    <a href="{{ route('follow-ups.index') }}" class="{{ request()->routeIs('follow-ups.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">event_upcoming</span>
                        <span>Follow-ups</span>
                    </a>
                    <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">bar_chart</span>
                        <span>Reports</span>
                    </a>
                    @if(in_array($activeRole ?? null, ['org_admin', 'superadmin'], true))
                        <a href="{{ route('settings.organization') }}" class="{{ request()->routeIs('settings.organization') ? 'active' : '' }}">
                            <span class="material-symbols-outlined">settings</span>
                            <span>Organization Settings</span>
                        </a>
                    @endif
                    <div class="crm-nav-divider">Pipeline &amp; Channels</div>
                    <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">business</span>
                        <span>Accounts</span>
                    </a>
                    <a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">contacts</span>
                        <span>Contacts</span>
                    </a>
                    <a href="{{ route('deals.index') }}" class="{{ request()->routeIs('deals.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">handshake</span>
                        <span>Deals</span>
                    </a>
                    <a href="{{ route('whatsapp.chat') }}" class="{{ request()->routeIs('whatsapp.chat') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">chat</span>
                        <span>WhatsApp Chat</span>
                    </a>
                    <a href="{{ route('analytics.executive') }}" class="{{ request()->routeIs('analytics.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">analytics</span>
                        <span>Analytics</span>
                    </a>
                @endif
                <a href="{{ route('organizations.index') }}" class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">corporate_fare</span>
                    <span>Organizations</span>
                </a>
                @if(isset($activeOrganization) && in_array($activeRole ?? null, ['org_admin', 'sales_manager', 'superadmin'], true))
                    <a href="{{ route('pipelines.index') }}" class="{{ request()->routeIs('pipelines.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">view_kanban</span>
                        <span>Pipelines</span>
                    </a>
                @endif
                @if(isset($activeOrganization) && in_array($activeRole ?? null, ['org_admin', 'superadmin'], true))
                    <a href="{{ route('settings.custom-fields') }}" class="{{ request()->routeIs('settings.custom-fields') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">tune</span>
                        <span>Custom Fields</span>
                    </a>
                    <a href="{{ route('whatsapp.settings') }}" class="{{ request()->routeIs('whatsapp.settings') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">api</span>
                        <span>WhatsApp API</span>
                    </a>
                    <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">history</span>
                        <span>Audit Logs</span>
                    </a>
                @endif
            </nav>
        </aside>

        <div class="crm-main">
            <header class="crm-topbar">
                <div class="crm-topbar-left">
                    <button type="button" id="crm-mobile-menu-btn" class="crm-mobile-menu-btn" aria-label="Open navigation">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    @if(isset($activeOrganization))
                        <strong>{{ $activeOrganization->name }}</strong>
                        @if(isset($activeRole))
                            <span class="crm-badge crm-badge-{{ $activeRole }}">{{ $activeRole }}</span>
                        @endif
                        <div class="crm-global-search">
                            <span class="material-symbols-outlined crm-global-search-icon">search</span>
                            <input type="search" id="crm-global-search-input" placeholder="Search leads, contacts, deals, campaigns..." autocomplete="off">
                            <div id="crm-global-search-results" class="crm-dropdown-panel" style="display:none;"></div>
                        </div>
                    @else
                        <span class="crm-muted">No active organization selected</span>
                    @endif
                </div>

                <div class="crm-topbar-right">
                    @if(isset($activeOrganization))
                        <div class="crm-notif-wrap">
                            <button type="button" id="crm-notif-btn" class="crm-icon-btn" aria-label="Notifications" title="Notifications">
                                <span class="material-symbols-outlined">notifications</span>
                                <span id="crm-notif-count" class="crm-notif-badge" style="display:none;">0</span>
                            </button>
                            <div id="crm-notif-panel" class="crm-dropdown-panel" style="display:none;"></div>
                        </div>
                    @endif
                    @livewire('tenancy.organization-switcher')
                    <span class="crm-user-pill">
                        <span class="crm-user-avatar">
                            <span class="material-symbols-outlined" style="font-size:17px;">person</span>
                        </span>
                        <span>{{ auth()->user()->name }}</span>
                    </span>
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

            <nav class="crm-bottom-nav" aria-label="Mobile Navigation">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">home</span>
                    <span>Home</span>
                </a>
                @if(isset($activeOrganization))
                    <a href="{{ route('leads.index') }}" class="{{ request()->routeIs('leads.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">inbox</span>
                        <span>Leads</span>
                    </a>
                    <a href="{{ route('deals.index') }}" class="{{ request()->routeIs('deals.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">handshake</span>
                        <span>Deals</span>
                    </a>
                    <a href="{{ route('whatsapp.chat') }}" class="{{ request()->routeIs('whatsapp.chat') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">chat</span>
                        <span>Chat</span>
                    </a>
                @else
                    <a href="{{ route('organizations.index') }}" class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">corporate_fare</span>
                        <span>Orgs</span>
                    </a>
                @endif
                <button type="button" id="crm-bottom-menu-btn">
                    <span class="material-symbols-outlined">menu</span>
                    <span>More</span>
                </button>
            </nav>
        </div>
    </div>
@else
    <div class="crm-auth-wrapper">
        <div class="crm-auth-ambient-tl" aria-hidden="true"></div>
        <div class="crm-auth-ambient-br" aria-hidden="true"></div>

        <div class="crm-auth-container @yield('auth_width_class')">
            <div class="crm-auth-brand">
                <div class="crm-auth-brand-badge">
                    <span class="material-symbols-outlined" style="font-size:28px;font-variation-settings:'FILL' 1;">dataset</span>
                </div>
                <div class="crm-auth-brand-text">
                    <span class="crm-auth-brand-name">{{ config('app.name', 'Modular CRM') }}</span>
                    <span class="crm-auth-brand-sub">B2B &amp; B2C Workspace</span>
                </div>
            </div>

            <div class="crm-auth-card">
                @if(session('status'))
                    <div class="crm-alert crm-alert-success">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="crm-alert crm-alert-error">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </div>

            <div class="crm-auth-footer-meta">
                <span>Multi-Tenant CRM</span>
                <span class="crm-auth-dot"></span>
                <span>256-bit SSL</span>
                <span class="crm-auth-dot"></span>
                <span>Enterprise Ready</span>
            </div>
        </div>
    </div>
@endauth

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/crm.js') }}"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
