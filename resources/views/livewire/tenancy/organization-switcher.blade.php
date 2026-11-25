<div>
    @if($organizations->isNotEmpty())
        <select
            id="org-switcher-select"
            class="crm-select"
            wire:change="switchOrganization($event.target.value)"
            style="min-width: 190px;"
            aria-label="Switch Organization"
        >
            @foreach($organizations as $org)
                <option value="{{ $org->id }}" {{ (int) $activeOrganizationId === (int) $org->id ? 'selected' : '' }}>
                    {{ $org->name }} ({{ $org->pivot->role }})
                </option>
            @endforeach
        </select>
    @endif
</div>
