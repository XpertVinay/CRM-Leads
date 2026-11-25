<div>
    <div class="crm-grid crm-grid-3">
        <div class="crm-card">
            <div class="crm-muted">Open Pipeline Value</div>
            <h2 style="margin:0.25rem 0;">${{ number_format($metrics['total_pipeline_value'], 2) }}</h2>
            <div class="crm-muted">Weighted: ${{ number_format($metrics['weighted_pipeline_value'], 2) }}</div>
        </div>
        <div class="crm-card">
            <div class="crm-muted">Win Rate</div>
            <h2 style="margin:0.25rem 0;">{{ $metrics['win_rate'] }}%</h2>
            <div class="crm-muted">{{ $metrics['won_count'] }} won / {{ $metrics['lost_count'] }} lost</div>
        </div>
        <div class="crm-card">
            <div class="crm-muted">Won Revenue</div>
            <h2 style="margin:0.25rem 0;">${{ number_format($metrics['won_revenue'], 2) }}</h2>
            <div class="crm-muted">Avg cycle: {{ $metrics['avg_sales_cycle_days'] }} days</div>
        </div>
    </div>

    <div class="crm-card">
        <h3>Stage Funnel</h3>
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Category</th>
                    <th>Deals</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($metrics['stage_funnel'] as $stage)
                    <tr>
                        <td>{{ $stage['stage_name'] }}</td>
                        <td><span class="crm-badge crm-badge-{{ $stage['category'] }}">{{ $stage['category'] }}</span></td>
                        <td>{{ $stage['deal_count'] }}</td>
                        <td>${{ number_format($stage['total_amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="crm-muted">No stages configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
