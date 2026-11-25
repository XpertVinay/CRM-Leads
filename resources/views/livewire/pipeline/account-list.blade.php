<div>
    <div class="crm-toolbar" style="margin-bottom:1rem;">
        <input type="text" wire:model.live.debounce.300ms="search" class="crm-input" placeholder="Search accounts..." style="max-width:300px;">
    </div>

    <table class="crm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Domain</th>
                <th>Industry</th>
                <th>Contacts</th>
                <th>Deals</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td><a href="{{ route('accounts.show', $account) }}">{{ $account->name }}</a></td>
                    <td>{{ $account->domain ?? '—' }}</td>
                    <td>{{ $account->industry ?? '—' }}</td>
                    <td>{{ $account->contacts_count }}</td>
                    <td>{{ $account->deals_count }}</td>
                    <td>{{ $account->created_at?->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="crm-muted">No accounts found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:1rem;">
        {{ $accounts->links() }}
    </div>
</div>
