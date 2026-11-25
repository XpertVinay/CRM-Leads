@extends('layouts.crm')

@section('title', 'Tracking Links — CRM')

@section('content')
    <h1 class="crm-page-title">Tracking Links &amp; Attribution</h1>
    <p class="crm-muted crm-mb-2">Create short links for campaigns. Clicks log raw traffic only and never create false lead or deal conversions; revenue is attributed strictly on Won deals.</p>

    @livewire('tracking.tracking-link-manager')

    @can('create', \App\Modules\Tracking\Models\TrackingLink::class)
        <div class="crm-card">
            <div class="crm-card-header">
                <h2 class="crm-card-title">Quick AJAX Link Creator</h2>
            </div>
            <form id="js-tracking-link-form">
                <div class="crm-grid crm-grid-3">
                    <div class="crm-form-group">
                        <label class="crm-label" for="tl_name">Name</label>
                        <input id="tl_name" class="crm-input" type="text" name="name" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="tl_code">Code (optional)</label>
                        <input id="tl_code" class="crm-input" type="text" name="code" placeholder="promo-2026">
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="tl_channel">Channel</label>
                        <select id="tl_channel" class="crm-select" name="channel">
                            @foreach($channels as $ch)
                                <option value="{{ $ch }}">{{ ucfirst($ch) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="tl_destination">Destination URL</label>
                        <input id="tl_destination" class="crm-input" type="url" name="destination_url" required>
                    </div>
                    <div class="crm-form-group">
                        <label class="crm-label" for="tl_spend">Ad Spend</label>
                        <input id="tl_spend" class="crm-input" type="number" step="0.01" min="0" name="ad_spend" value="0">
                    </div>
                </div>
                <button type="submit" class="crm-btn crm-btn-secondary">Create via AJAX</button>
            </form>
            <div id="js-tl-result" class="crm-mt-1"></div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
$(function () {
    $('#js-tracking-link-form').on('submit', function (e) {
        e.preventDefault();
        $.post('/ajax/tracking-links', $(this).serialize())
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.CrmUi.renderErrors($('#js-tl-result'), xhr); });
    });
});
</script>
@endpush
