@extends('layouts.crm')

@section('title', $lead->fullName().' — Lead Detail')

@section('content')
    <div class="crm-between crm-mb-2">
        <div>
            <h1 class="crm-page-title">{{ $lead->fullName() }}</h1>
            <p class="crm-muted">
                <span class="crm-badge {{ $lead->isB2B() ? 'crm-badge-b2b' : 'crm-badge-b2c' }}">{{ $lead->type }}</span>
                <span class="crm-badge">{{ ucfirst($lead->status) }}</span>
                Source: {{ $lead->source }}
            </p>
        </div>
        <a href="{{ route('leads.index') }}" class="crm-btn crm-btn-secondary">Back to Leads</a>
    </div>

    <div class="crm-grid crm-grid-2">
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Lead Profile</h2>
            </div>
            <p><strong>Email:</strong> {{ $lead->email ?: '—' }}</p>
            <p><strong>Phone:</strong> {{ $lead->phone ?: '—' }}</p>
            @if($lead->isB2B())
                <p><strong>Company:</strong> {{ $lead->company_name ?: '—' }}</p>
                <p><strong>Job Title:</strong> {{ $lead->job_title ?: '—' }}</p>
                <p><strong>Employees:</strong> {{ $lead->employee_count ?: '—' }}</p>
            @else
                <p><strong>Location:</strong> {{ $lead->location ?: '—' }}</p>
            @endif
            <p><strong>Assigned Owner:</strong> {{ $lead->assignedUser?->name ?? 'Unassigned' }}</p>

            @if(! empty($lead->custom_fields))
                <h3 class="crm-card-title crm-mt-2">Custom Fields</h3>
                @foreach($lead->custom_fields as $key => $val)
                    <p><strong>{{ $key }}:</strong> {{ is_array($val) ? implode(', ', $val) : (is_bool($val) ? ($val ? 'Yes' : 'No') : $val) }}</p>
                @endforeach
            @endif
        </div>

        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Conversion &amp; Attribution</h2>
            </div>
            @if($lead->isConverted())
                <div class="crm-alert crm-alert-success">
                    Converted on {{ $lead->converted_at?->toDateTimeString() }}.
                </div>
                @if($lead->convertedAccount)
                    <p><strong>Account:</strong> {{ $lead->convertedAccount->name }}</p>
                @endif
                @if($lead->convertedContact)
                    <p><strong>Contact:</strong> {{ $lead->convertedContact->fullName() }}</p>
                @endif
                @if($lead->convertedDeal)
                    <p><strong>Deal:</strong> {{ $lead->convertedDeal->title }} ({{ $lead->convertedDeal->currency }} {{ $lead->convertedDeal->amount }})</p>
                @endif
            @else
                @can('convert', $lead)
                    <form id="js-convert-lead-form">
                        @if($lead->isB2B())
                            <div class="crm-form-group">
                                <label class="crm-label" for="conv_account_name">Account Name</label>
                                <input id="conv_account_name" class="crm-input" type="text" name="account_name" value="{{ $lead->company_name }}">
                            </div>
                        @endif
                        <div class="crm-form-group">
                            <label class="crm-flex">
                                <input type="checkbox" name="create_deal" value="1" checked>
                                <span>Create Deal</span>
                            </label>
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label" for="conv_deal_title">Deal Title</label>
                            <input id="conv_deal_title" class="crm-input" type="text" name="deal_title" value="{{ $lead->isB2B() && $lead->company_name ? $lead->company_name.' — '.$lead->fullName() : $lead->fullName() }}">
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label" for="conv_deal_amount">Deal Amount</label>
                            <input id="conv_deal_amount" class="crm-input" type="number" step="0.01" min="0" name="deal_amount" value="0">
                        </div>
                        <button type="submit" class="crm-btn crm-btn-primary">Convert Lead</button>
                    </form>
                    <div id="js-convert-result" class="crm-mt-1"></div>
                @endcan
            @endif

            <h3 class="crm-card-title crm-mt-2">Touchpoint Journey</h3>
            @forelse($lead->touchpoints as $tp)
                <p class="crm-muted">
                    <strong>{{ $tp->event_milestone }}</strong> — {{ $tp->trackingLink?->name ?? 'Direct' }} ({{ $tp->occurred_at?->toDateTimeString() }})
                </p>
            @empty
                <p class="crm-muted">No tracking link touchpoints recorded for this lead.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#js-convert-lead-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/leads/{{ $lead->id }}/convert', $(this).serialize())
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-convert-result'), xhr); });
    });
});
</script>
@endpush
