@extends('layouts.crm')

@section('title', 'Sales Rep Productivity')

@section('content')
<div class="crm-page-header">
    <h1>Sales Rep Productivity</h1>
    <a href="{{ route('analytics.executive') }}" class="crm-btn crm-btn-secondary crm-btn-sm">&larr; Executive</a>
</div>

<div class="crm-card">
    <table class="crm-table">
        <thead>
            <tr>
                <th>Rep</th>
                <th>Assigned Leads</th>
                <th>Open Deals</th>
                <th>Won Deals</th>
                <th>Won Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reps as $rep)
                <tr>
                    <td>{{ $rep['name'] }} <span class="crm-muted">{{ $rep['email'] }}</span></td>
                    <td>{{ $rep['leads_count'] }}</td>
                    <td>{{ $rep['open_deals_count'] }}</td>
                    <td>{{ $rep['won_deals_count'] }}</td>
                    <td>${{ number_format($rep['won_revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="crm-muted">No team members found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
