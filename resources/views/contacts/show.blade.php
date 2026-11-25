@extends('layouts.crm')

@section('title', $contact->fullName() . ' — Contact')

@section('content')
<div class="crm-page-header">
    <div>
        <a href="{{ route('contacts.index') }}" class="crm-back-link">&larr; Contacts</a>
        <h1>{{ $contact->fullName() }}</h1>
    </div>
</div>

<div class="crm-detail-grid">
    <div class="crm-card">
        <h3>Contact Details</h3>
        <dl class="crm-dl">
            <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
            <dt>Phone</dt><dd>{{ $contact->phone ?? '—' }}</dd>
            <dt>Job Title</dt><dd>{{ $contact->job_title ?? '—' }}</dd>
            <dt>Preferred Channel</dt><dd>{{ $contact->preferred_channel ?? 'email' }}</dd>
            <dt>Account</dt>
            <dd>
                @if($contact->account)
                    <a href="{{ route('accounts.show', $contact->account) }}">{{ $contact->account->name }}</a>
                @else
                    —
                @endif
            </dd>
            <dt>Created</dt><dd>{{ $contact->created_at?->format('M j, Y') }}</dd>
        </dl>
        @if(!empty($contact->custom_fields))
        <h4>Custom Fields</h4>
        <dl class="crm-dl">
            @foreach($contact->custom_fields as $key => $val)
                <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                <dd>{{ is_array($val) ? implode(', ', $val) : $val }}</dd>
            @endforeach
        </dl>
        @endif
    </div>

    <div class="crm-card">
        <h3>Deals ({{ $contact->deals->count() }})</h3>
        @forelse($contact->deals as $deal)
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
    <div id="activity-timeline" data-type="contact" data-id="{{ $contact->id }}"></div>
</div>

@push('scripts')
<script>
$(function() { CRM.loadTimeline('#activity-timeline'); });
</script>
@endpush
@endsection
