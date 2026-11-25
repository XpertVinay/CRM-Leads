@extends('layouts.crm')

@section('title', 'Sign In — CRM')

@section('content')
    <h1 class="crm-page-title">Sign In</h1>
    <p class="crm-muted crm-mb-2">Access your organization CRM workspace.</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="crm-form-group">
            <label class="crm-label" for="email">Email</label>
            <input id="email" class="crm-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="crm-form-group">
            <label class="crm-label" for="password">Password</label>
            <input id="password" class="crm-input" type="password" name="password" required>
        </div>

        <div class="crm-form-group">
            <label class="crm-flex">
                <input type="checkbox" name="remember" value="1">
                <span>Remember me</span>
            </label>
        </div>

        <div class="crm-between">
            <button type="submit" class="crm-btn crm-btn-primary">Sign In</button>
            <a href="{{ route('register') }}">Create Organization</a>
        </div>
    </form>
@endsection
