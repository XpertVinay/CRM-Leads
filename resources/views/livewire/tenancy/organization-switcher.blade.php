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
                @php
                    $roleLabel = $org->pivot?->role ?? ($user?->roleIn($org) ?? 'superadmin');
                @endphp
                <option value="{{ $org->id }}" {{ (int) $activeOrganizationId === (int) $org->id ? 'selected' : '' }}>
                    {{ $org->name }} ({{ $roleLabel }})
                </option>
            @endforeach
        </select>
    @endif
</div>
