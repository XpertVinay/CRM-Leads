@extends('layouts.crm')

@section('title', 'Executive Pipeline Analytics')

@section('content')
<div class="crm-page-header">
    <h1>Executive Pipeline Dashboard</h1>
    <div class="crm-flex">
        <a href="{{ route('analytics.attribution') }}" class="crm-btn crm-btn-secondary crm-btn-sm">Attribution ROI</a>
        <a href="{{ route('analytics.productivity') }}" class="crm-btn crm-btn-secondary crm-btn-sm">Sales Productivity</a>
        <a href="{{ route('analytics.whatsapp-health') }}" class="crm-btn crm-btn-secondary crm-btn-sm">WhatsApp Health</a>
    </div>
</div>

@livewire('analytics.funnel-velocity-dashboard')
@endsection
