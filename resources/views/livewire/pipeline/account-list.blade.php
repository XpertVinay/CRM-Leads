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
                @if(auth()->user()?->isSuperadmin())
                    <th>Actions</th>
                @endif
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
                    @if(auth()->user()?->isSuperadmin())
                        <td>
                            <button type="button" class="crm-btn crm-btn-secondary crm-btn-sm" onclick="CRM.openEditAccount({{ $account->id }}, @js($account->name), @js($account->domain ?? ''), @js($account->industry ?? ''), @js($account->annual_revenue ?? ''), @js($account->employee_count ?? ''))">Edit</button>
                            <button type="button" class="crm-btn crm-btn-danger crm-btn-sm" onclick="CRM.deleteAccount({{ $account->id }})">Delete</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()?->isSuperadmin() ? 7 : 6 }}" class="crm-muted">No accounts found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:1rem;">
        {{ $accounts->links() }}
    </div>
</div>
