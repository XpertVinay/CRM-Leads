@extends('layouts.crm')

@section('title', 'Register — CRM')
@section('auth_width_class', 'crm-auth-wide')

@section('content')
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <div class="crm-pill-badge">
            <span class="material-symbols-outlined" style="font-size: 16px; font-variation-settings: 'FILL' 1;">bolt</span>
            <span>Get started in 30 seconds</span>
        </div>
        <h1 class="crm-page-title" style="font-size: 1.65rem;">Create Account &amp; Organization</h1>
        <p class="crm-muted" style="margin: 0;">Provision a new tenant workspace as Org Admin.</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="crm-section-label" style="margin-top: 0;">Account Details</div>

        <div class="crm-form-group">
            <label class="crm-label" for="name">Full Name</label>
            <div class="crm-input-wrap">
                <span class="material-symbols-outlined crm-input-icon">person</span>
                <input id="name" class="crm-input" type="text" name="name" value="{{ old('name') }}" placeholder="Alex Morgan" required>
            </div>
        </div>

        <div class="crm-form-row">
            <div class="crm-form-group">
                <label class="crm-label" for="email">Work Email</label>
                <div class="crm-input-wrap">
                    <span class="material-symbols-outlined crm-input-icon">mail</span>
                    <input id="email" class="crm-input" type="email" name="email" value="{{ old('email') }}" placeholder="alex@example.com" required>
                </div>
            </div>

            <div class="crm-form-group">
                <label class="crm-label" for="phone">Phone (Optional)</label>
                <div class="crm-input-wrap">
                    <span class="material-symbols-outlined crm-input-icon">call</span>
                    <input id="phone" class="crm-input" type="text" name="phone" value="{{ old('phone') }}" placeholder="+1 555 0100">
                </div>
            </div>
        </div>

        <div class="crm-section-label">Organization Workspace</div>

        <div class="crm-form-row">
            <div class="crm-form-group">
                <label class="crm-label" for="organization_name">Organization Name</label>
                <div class="crm-input-wrap">
                    <span class="material-symbols-outlined crm-input-icon">corporate_fare</span>
                    <input id="organization_name" class="crm-input" type="text" name="organization_name" value="{{ old('organization_name') }}" placeholder="Acme Corp" required>
                </div>
            </div>

            <div class="crm-form-group">
                <label class="crm-label" for="organization_slug">Organization Slug (Optional)</label>
                <input id="organization_slug" class="crm-input" type="text" name="organization_slug" value="{{ old('organization_slug') }}" placeholder="e.g. acme-corp">
            </div>
        </div>

        <div class="crm-form-row">
            <div class="crm-form-group">
                <label class="crm-label" for="registration_identifier">Registration Identifier (Optional)</label>
                <input id="registration_identifier" class="crm-input" type="text" name="registration_identifier" value="{{ old('registration_identifier') }}">
            </div>

            <div class="crm-form-group">
                <label class="crm-label" for="primary_contact_email">Primary Contact Email (Optional)</label>
                <input id="primary_contact_email" class="crm-input" type="email" name="primary_contact_email" value="{{ old('primary_contact_email') }}">
            </div>
        </div>

        <div class="crm-form-row">
            <div class="crm-form-group">
                <label class="crm-label" for="timezone">Timezone (Optional)</label>
                <input id="timezone" class="crm-input" type="text" name="timezone" value="{{ old('timezone') }}" placeholder="UTC">
            </div>

            <div class="crm-form-group">
                <label class="crm-label" for="currency">Currency (Optional)</label>
                <input id="currency" class="crm-input" type="text" name="currency" value="{{ old('currency') }}" placeholder="USD" maxlength="3">
            </div>
        </div>

        <div class="crm-section-label">Security</div>

        <div class="crm-form-group">
            <label class="crm-label" for="password">Password</label>
            <div class="crm-input-wrap">
                <span class="material-symbols-outlined crm-input-icon">lock</span>
                <input id="password" class="crm-input" type="password" name="password" placeholder="Create a password" data-password-strength="#crm-reg-strength" required>
                <button type="button" class="crm-input-action" data-toggle-password="#password" aria-label="Toggle password visibility">
                    <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                </button>
            </div>
            <div id="crm-reg-strength" class="crm-strength-grid">
                <div class="crm-strength-bar"></div>
                <div class="crm-strength-bar"></div>
                <div class="crm-strength-bar"></div>
                <div class="crm-strength-bar"></div>
            </div>
            <p class="crm-muted" style="font-size: 0.75rem; margin: 0.35rem 0 0;">Use 8+ characters with a mix of letters &amp; numbers</p>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="password_confirmation">Confirm Password</label>
            <div class="crm-input-wrap">
                <span class="material-symbols-outlined crm-input-icon">lock_reset</span>
                <input id="password_confirmation" class="crm-input" type="password" name="password_confirmation" placeholder="Confirm your password" required>
            </div>
        </div>

        <button type="submit" class="crm-btn crm-btn-primary crm-btn-lg crm-btn-block crm-mt-1">
            <span>Register &amp; Provision</span>
            <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
        </button>
    </form>

    <div class="crm-auth-divider">
        <span>or sign up with</span>
    </div>

    <div class="crm-social-grid">
        <button type="button" class="crm-social-btn" title="Google Sign-Up (Coming Soon)">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z" fill="#4285F4"/>
                <path d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z" fill="#34A853"/>
                <path d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z" fill="#FBBC05"/>
                <path d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z" fill="#EA4335"/>
            </svg>
            <span>Google</span>
        </button>
        <button type="button" class="crm-social-btn" title="Apple Sign-Up (Coming Soon)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.42c.62-.75 1.04-1.8 0.93-2.85-.9.04-1.99.6-2.63 1.35-.57.65-1.07 1.71-.94 2.73 1 .08 2.02-.48 2.64-1.23z"/>
            </svg>
            <span>Apple</span>
        </button>
    </div>

    <div style="margin-top: 1.35rem; text-align: center;">
        <span class="crm-muted">Already registered?</span>
        <a href="{{ route('login') }}" style="font-weight: 600; margin-left: 0.25rem;">Sign in</a>
    </div>
@endsection
