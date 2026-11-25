@extends('layouts.crm')

@section('title', 'Dashboard — CRM')

@section('content')
    <div class="crm-between crm-mb-2">
        <div>
            <h1 class="crm-page-title">Workspace Dashboard</h1>
            <p class="crm-muted">Active organization: <strong>{{ $organization->name }}</strong> ({{ $organization->slug }})</p>
        </div>
        <span class="crm-badge crm-badge-{{ $role }}">{{ $role }}</span>
    </div>

    <div class="crm-grid crm-grid-3">
        <div class="crm-card">
            <div class="crm-muted">Team Members</div>
            <div style="font-size: 1.75rem; font-weight: 700;">{{ $membersCount }}</div>
        </div>
        <div class="crm-card">
            <div class="crm-muted">Custom Field Definitions</div>
            <div style="font-size: 1.75rem; font-weight: 700;">{{ $customFieldsCount }}</div>
        </div>
        <div class="crm-card">
            <div class="crm-muted">Currency / Timezone</div>
            <div style="font-size: 1.25rem; font-weight: 700;">{{ $organization->currency }} / {{ $organization->timezone }}</div>
        </div>
    </div>
@endsection
