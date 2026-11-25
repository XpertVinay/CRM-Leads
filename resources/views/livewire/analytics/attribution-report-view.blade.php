<div>
    <div class="crm-toolbar" style="margin-bottom:1rem;">
        <label class="crm-label" style="margin:0;">Attribution Model:</label>
        <select wire:model.live="model" class="crm-select" style="max-width:220px;">
            <option value="first_touch">First Touch</option>
            <option value="last_touch">Last Touch</option>
            <option value="linear">Linear</option>
        </select>
    </div>

    <div class="crm-card">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Clicks</th>
                    <th>Leads</th>
                    <th>Conversations</th>
                    <th>Won Deals</th>
                    <th>Ad Spend</th>
                    <th>Revenue</th>
                    <th>CPA</th>
                    <th>ROAS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['campaigns'] as $row)
                    <tr>
                        <td>{{ $row['name'] }} <span class="crm-muted">({{ $row['code'] }})</span></td>
                        <td>{{ $row['clicks'] }}</td>
                        <td>{{ $row['leads'] }}</td>
                        <td>{{ $row['qualified_conversations'] }}</td>
                        <td>{{ $row['won_deals'] }}</td>
                        <td>${{ number_format($row['ad_spend'], 2) }}</td>
                        <td>${{ number_format($row['revenue'], 2) }}</td>
                        <td>${{ number_format($row['cpa'], 2) }}</td>
                        <td>{{ $row['roas'] }}x</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="crm-muted">No tracking links yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
