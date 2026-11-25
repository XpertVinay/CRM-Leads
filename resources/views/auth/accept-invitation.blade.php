@extends('layouts.crm')

@section('title', 'Accept Invitation — CRM')

@section('content')
    <div style="margin-bottom: 1.5rem;">
        <div class="crm-pill-badge">
            <span class="material-symbols-outlined" style="font-size: 16px;">mail</span>
            <span>Team Invitation</span>
        </div>
        <h1 class="crm-page-title" style="font-size: 1.5rem;">Join {{ $invitation->organization->name }}</h1>
        <p class="crm-muted" style="margin: 0;">
            Invited email: <strong>{{ $invitation->email }}</strong> &middot;
            Role: <span class="crm-badge crm-badge-{{ $invitation->role }}">{{ $invitation->role }}</span>
        </p>
    </div>

    @if($invitation->isAccepted())
        <div class="crm-alert crm-alert-warning">This invitation has already been accepted.</div>
    @elseif($invitation->isExpired())
        <div class="crm-alert crm-alert-error">This invitation has expired.</div>
    @else
        <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
            @csrf
            @guest
                <div class="crm-form-group">
                    <label class="crm-label" for="name">Full Name (new accounts)</label>
                    <div class="crm-input-wrap">
                        <span class="material-symbols-outlined crm-input-icon">person</span>
                        <input id="name" class="crm-input" type="text" name="name" value="{{ old('name') }}" placeholder="Alex Morgan">
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
                <div class="crm-form-group">
                    <label class="crm-label" for="password_confirmation">Confirm Password (new accounts)</label>
                    <div class="crm-input-wrap">
                        <span class="material-symbols-outlined crm-input-icon">lock_reset</span>
                        <input id="password_confirmation" class="crm-input" type="password" name="password_confirmation" placeholder="Confirm your password">
                    </div>
                </div>
            @endguest

            <button type="submit" class="crm-btn crm-btn-primary crm-btn-lg crm-btn-block">
                <span>Accept Invitation</span>
                <span class="material-symbols-outlined" style="font-size: 19px;">arrow_forward</span>
            </button>
        </form>
    @endif
@endsection
