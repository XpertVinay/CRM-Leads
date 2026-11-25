@extends('layouts.crm')

@section('title', 'Register — CRM')

@section('content')
    <h1 class="crm-page-title">Create Account &amp; Organization</h1>
    <p class="crm-muted crm-mb-2">Provision a new tenant workspace as Org Admin.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="crm-form-group">
            <label class="crm-label" for="name">Full Name</label>
            <input id="name" class="crm-input" type="text" name="name" value="{{ old('name') }}" required>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="email">Work Email</label>
            <input id="email" class="crm-input" type="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="phone">Phone (Optional)</label>
            <input id="phone" class="crm-input" type="text" name="phone" value="{{ old('phone') }}">
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="organization_name">Organization Name</label>
            <input id="organization_name" class="crm-input" type="text" name="organization_name" value="{{ old('organization_name') }}" required>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="organization_slug">Organization Slug (Optional)</label>
            <input id="organization_slug" class="crm-input" type="text" name="organization_slug" value="{{ old('organization_slug') }}" placeholder="e.g. acme-corp">
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="registration_identifier">Registration Identifier (Optional)</label>
            <input id="registration_identifier" class="crm-input" type="text" name="registration_identifier" value="{{ old('registration_identifier') }}">
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="primary_contact_email">Primary Contact Email (Optional)</label>
            <input id="primary_contact_email" class="crm-input" type="email" name="primary_contact_email" value="{{ old('primary_contact_email') }}">
        </div>

        <div class="crm-grid crm-grid-2">
            <div class="crm-form-group">
                <label class="crm-label" for="timezone">Timezone (Optional)</label>
                <input id="timezone" class="crm-input" type="text" name="timezone" value="{{ old('timezone') }}" placeholder="UTC">
            </div>

            <div class="crm-form-group">
                <label class="crm-label" for="currency">Currency (Optional)</label>
                <input id="currency" class="crm-input" type="text" name="currency" value="{{ old('currency') }}" placeholder="USD" maxlength="3">
            </div>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="password">Password</label>
            <input id="password" class="crm-input" type="password" name="password" required>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" class="crm-input" type="password" name="password_confirmation" required>
        </div>

        <div class="crm-between">
            <button type="submit" class="crm-btn crm-btn-primary">Register &amp; Provision</button>
            <a href="{{ route('login') }}">Already registered?</a>
        </div>
    </form>
@endsection
