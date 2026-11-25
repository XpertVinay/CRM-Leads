@extends('layouts.crm')

@section('title', 'Sign In — CRM')

@section('content')
    <div style="margin-bottom: 1.5rem;">
        <h1 class="crm-page-title" style="font-size: 1.5rem;">Welcome back</h1>
        <p class="crm-muted" style="margin: 0;">Access your organization CRM workspace.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="crm-form-group">
            <label class="crm-label" for="email">Email</label>
            <div class="crm-input-wrap">
                <span class="material-symbols-outlined crm-input-icon">mail</span>
                <input id="email" class="crm-input" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </div>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="password">Password</label>
            <div class="crm-input-wrap">
                <span class="material-symbols-outlined crm-input-icon">lock</span>
                <input id="password" class="crm-input" type="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="crm-input-action" data-toggle-password="#password" aria-label="Toggle password visibility">
                    <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                </button>
            </div>
        </div>

        <div class="crm-between crm-mb-2" style="padding-top: 0.2rem;">
            <label class="crm-flex" style="cursor: pointer;">
                <input type="checkbox" name="remember" value="1" style="accent-color: var(--crm-primary-container); width: 16px; height: 16px;">
                <span class="crm-muted" style="font-size: 0.82rem;">Remember me</span>
            </label>
            <span class="crm-ssl-pill">
                <span class="material-symbols-outlined" style="font-size: 14px; color: var(--crm-primary); font-variation-settings: 'FILL' 1;">verified_user</span>
                256-bit SSL
            </span>
        </div>

        <button type="submit" class="crm-btn crm-btn-primary crm-btn-lg crm-btn-block">
            <span>Sign In</span>
            <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
        </button>
    </form>

    <div class="crm-auth-divider">
        <span>or continue with</span>
    </div>

    <div class="crm-social-grid">
        <button type="button" class="crm-social-btn" title="Google Sign-In (Coming Soon)">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
            </svg>
            <span>Google</span>
        </button>
        <button type="button" class="crm-social-btn" title="Apple Sign-In (Coming Soon)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.42c.62-.76 1.05-1.81.93-2.87-.91.04-2.02.61-2.67 1.37-.58.67-1.09 1.74-.95 2.78 1.02.08 2.07-.52 2.69-1.28z"/>
            </svg>
            <span>Apple</span>
        </button>
    </div>

    <div style="margin-top: 1.35rem; text-align: center;">
        <span class="crm-muted">Don't have an account?</span>
        <a href="{{ route('register') }}" style="font-weight: 600; margin-left: 0.25rem;">Create Organization</a>
    </div>
@endsection
