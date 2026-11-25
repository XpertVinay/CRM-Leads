@extends('layouts.crm')

@section('title', 'WhatsApp Channel Health')

@section('content')
<div class="crm-page-header">
    <h1>WhatsApp Channel Health</h1>
    <a href="{{ route('analytics.executive') }}" class="crm-btn crm-btn-secondary crm-btn-sm">&larr; Executive</a>
</div>

<div class="crm-grid crm-grid-3">
    <div class="crm-card">
        <div class="crm-muted">Delivery Rate</div>
        <h2 style="margin:0.25rem 0;">{{ $health['delivery_rate'] }}%</h2>
        <div class="crm-muted">{{ $health['delivered_count'] }} / {{ $health['outbound_count'] }} outbound</div>
    </div>
    <div class="crm-card">
        <div class="crm-muted">Read Rate</div>
        <h2 style="margin:0.25rem 0;">{{ $health['read_rate'] }}%</h2>
        <div class="crm-muted">{{ $health['read_count'] }} read</div>
    </div>
    <div class="crm-card">
        <div class="crm-muted">24h Active Windows</div>
        <h2 style="margin:0.25rem 0;">{{ $health['active_windows_count'] }}</h2>
        <div class="crm-muted">{{ $health['expired_windows_count'] }} expired</div>
    </div>
</div>
@endsection
