@extends('layouts.crm')

@section('title', $account->name . ' — Account')

@section('content')
<div class="crm-page-header">
    <div>
        <a href="{{ route('accounts.index') }}" class="crm-back-link">&larr; Accounts</a>
        <h1>{{ $account->name }}</h1>
    </div>
</div>

<div class="crm-detail-grid">
    <div class="crm-card">
        <h3>Account Details</h3>
        <dl class="crm-dl">
            <dt>Domain</dt><dd>{{ $account->domain ?? '—' }}</dd>
            <dt>Industry</dt><dd>{{ $account->industry ?? '—' }}</dd>
            <dt>Annual Revenue</dt><dd>{{ $account->annual_revenue ? '$'.number_format((float) $account->annual_revenue, 2) : '—' }}</dd>
            <dt>Employees</dt><dd>{{ $account->employee_count ?? '—' }}</dd>
            <dt>Created</dt><dd>{{ $account->created_at?->format('M j, Y') }}</dd>
        </dl>
        @if(!empty($account->custom_fields))
        <h4>Custom Fields</h4>
        <dl class="crm-dl">
            @foreach($account->custom_fields as $key => $val)
                <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                <dd>{{ is_array($val) ? implode(', ', $val) : $val }}</dd>
            @endforeach
        </dl>
        @endif
    </div>

    <div class="crm-card">
        <h3>Contacts ({{ $account->contacts->count() }})</h3>
        @forelse($account->contacts as $contact)
            <div class="crm-list-item">
                <a href="{{ route('contacts.show', $contact) }}">{{ $contact->fullName() }}</a>
                <span class="crm-muted">{{ $contact->email }}</span>
            </div>
        @empty
            <p class="crm-muted">No contacts yet.</p>
        @endforelse
    </div>

    <div class="crm-card">
        <h3>Deals ({{ $account->deals->count() }})</h3>
        @forelse($account->deals as $deal)
            <div class="crm-list-item">
                <a href="{{ route('deals.show', $deal) }}">{{ $deal->title }}</a>
                <span class="crm-badge">{{ $deal->pipelineStage?->name ?? 'N/A' }}</span>
                <span>${{ number_format((float) $deal->amount, 2) }}</span>
            </div>
        @empty
            <p class="crm-muted">No deals yet.</p>
        @endforelse
    </div>
</div>

<div class="crm-card" style="margin-top: 1rem;">
    <h3>Activity Timeline</h3>
    <div id="activity-timeline" data-type="account" data-id="{{ $account->id }}"></div>
</div>

@push('scripts')
<script>
$(function() {
    CRM.loadTimeline('#activity-timeline');
});
</script>
@endpush
@endsection
