@extends('layouts.crm')

@section('title', 'Attribution & Campaign ROI')

@section('content')
<div class="crm-page-header">
    <h1>Attribution &amp; Campaign ROI</h1>
    <a href="{{ route('analytics.executive') }}" class="crm-btn crm-btn-secondary crm-btn-sm">&larr; Executive</a>
</div>

@livewire('analytics.attribution-report-view')
@endsection
