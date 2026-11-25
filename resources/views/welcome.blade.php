<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Modular B2B & B2C CRM') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/crm.css') }}">
</head>
<body>
<div class="crm-auth-wrapper">
    <div class="crm-auth-card" style="max-width: 620px;">
        <h1 class="crm-page-title">Modular B2B &amp; B2C CRM</h1>
        <p class="crm-muted crm-mb-2">
            Multi-tenant B2B &amp; B2C CRM built with Laravel 13, Livewire 3, and shared-server static assets.
        </p>

        <div class="crm-flex crm-mt-2">
            @auth
                <a href="{{ url('/dashboard') }}" class="crm-btn crm-btn-primary">Go to Dashboard</a>
                <a href="{{ route('organizations.index') }}" class="crm-btn crm-btn-secondary">Organizations</a>
            @else
                <a href="{{ route('login') }}" class="crm-btn crm-btn-primary">Log In</a>
                <a href="{{ route('register') }}" class="crm-btn crm-btn-secondary">Register Organization</a>
            @endauth
        </div>
    </div>
</div>
</body>
</html>
