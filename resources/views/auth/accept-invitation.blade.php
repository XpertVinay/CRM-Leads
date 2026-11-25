@extends('layouts.crm')

@section('title', 'Accept Invitation — CRM')

@section('content')
    <h1 class="crm-page-title">Join {{ $invitation->organization->name }}</h1>
    <p class="crm-muted crm-mb-2">
        Invited email: <strong>{{ $invitation->email }}</strong> |
        Role: <span class="crm-badge crm-badge-{{ $invitation->role }}">{{ $invitation->role }}</span>
    </p>

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
                    <input id="name" class="crm-input" type="text" name="name" value="{{ old('name') }}">
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="password">Password</label>
                    <input id="password" class="crm-input" type="password" name="password" required>
                </div>
                <div class="crm-form-group">
                    <label class="crm-label" for="password_confirmation">Confirm Password (new accounts)</label>
                    <input id="password_confirmation" class="crm-input" type="password" name="password_confirmation">
                </div>
            @endguest

            <button type="submit" class="crm-btn crm-btn-primary">Accept Invitation</button>
        </form>
    @endif
@endsection
