@extends('layouts.crm')

@section('title', $deal->title . ' — Deal')

@section('content')
<div class="crm-page-header">
    <div>
        <a href="{{ route('deals.index') }}" class="crm-back-link">&larr; Deals</a>
        <h1>{{ $deal->title }}</h1>
        @if($deal->is_stale)
            <span class="crm-badge crm-badge-warning">⚠ Stale</span>
        @endif
    </div>
</div>

<div class="crm-detail-grid">
    <div class="crm-card">
        <h3>Deal Details</h3>
        <dl class="crm-dl">
            <dt>Amount</dt><dd>{{ $deal->currency }} {{ number_format((float) $deal->amount, 2) }}</dd>
            <dt>Pipeline</dt><dd>{{ $deal->pipeline?->name ?? '—' }}</dd>
            <dt>Stage</dt>
            <dd>
                <span class="crm-badge crm-badge-{{ $deal->pipelineStage?->category ?? 'open' }}">
                    {{ $deal->pipelineStage?->name ?? 'N/A' }}
                </span>
            </dd>
            <dt>Stage Entered</dt><dd>{{ $deal->stage_entered_at?->format('M j, Y H:i') ?? '—' }}</dd>
            <dt>Expected Close</dt><dd>{{ $deal->expected_close_date?->format('M j, Y') ?? '—' }}</dd>
            <dt>Closed At</dt><dd>{{ $deal->closed_at?->format('M j, Y H:i') ?? '—' }}</dd>
            @if($deal->lost_reason)
                <dt>Lost Reason</dt><dd>{{ $deal->lost_reason }}</dd>
            @endif
            <dt>Assigned To</dt><dd>{{ $deal->assignedUser?->name ?? 'Unassigned' }}</dd>
        </dl>
    </div>

    <div class="crm-card">
        <h3>Contact</h3>
        @if($deal->contact)
            <p><a href="{{ route('contacts.show', $deal->contact) }}">{{ $deal->contact->fullName() }}</a></p>
            <p class="crm-muted">{{ $deal->contact->email }}</p>
        @else
            <p class="crm-muted">No contact linked.</p>
        @endif

        <h3 style="margin-top:1rem;">Account</h3>
        @if($deal->account)
            <p><a href="{{ route('accounts.show', $deal->account) }}">{{ $deal->account->name }}</a></p>
        @else
            <p class="crm-muted">No account linked.</p>
        @endif
    </div>

    @if(!empty($deal->custom_fields))
    <div class="crm-card">
        <h3>Custom Fields</h3>
        <dl class="crm-dl">
            @foreach($deal->custom_fields as $key => $val)
                <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                <dd>{{ is_array($val) ? implode(', ', $val) : $val }}</dd>
            @endforeach
        </dl>
    </div>
    @endif
</div>

<div class="crm-card" style="margin-top: 1rem;">
    <h3>Activity Timeline</h3>
    <div id="activity-timeline" data-type="deal" data-id="{{ $deal->id }}"></div>
</div>

@push('scripts')
<script>
$(function() { CRM.loadTimeline('#activity-timeline'); });
</script>
@endpush
@endsection
