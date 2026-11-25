<div>
    <div class="crm-card">
        <div class="crm-card-header">
            <div class="crm-flex">
                @foreach($entityTypes as $type)
                    <button
                        type="button"
                        wire:click="setEntityType('{{ $type }}')"
                        class="crm-btn crm-btn-sm {{ $entityType === $type ? 'crm-btn-primary' : 'crm-btn-secondary' }}"
                    >
                        {{ ucfirst($type) }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($statusMessage)
            <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
        @endif

        <form wire:submit="createField" class="crm-mb-2">
            <div class="crm-grid crm-grid-3">
                <div class="crm-form-group">
                    <label class="crm-label">Field Key</label>
                    <input type="text" class="crm-input" wire:model="fieldKey" placeholder="industry">
                    @error('fieldKey') <div class="crm-error">{{ $message }}</div> @enderror
                </div>
                <div class="crm-form-group">
                    <label class="crm-label">Label</label>
                    <input type="text" class="crm-input" wire:model="label" placeholder="Industry">
                    @error('label') <div class="crm-error">{{ $message }}</div> @enderror
                </div>
                <div class="crm-form-group">
                    <label class="crm-label">Data Type</label>
                    <select class="crm-select" wire:model.live="dataType">
                        @foreach($dataTypes as $dt)
                            <option value="{{ $dt }}">{{ $dt }}</option>
                        @endforeach
                    </select>
                </div>
                @if(in_array($dataType, ['select', 'multiselect'], true))
                    <div class="crm-form-group">
                        <label class="crm-label">Options (comma-separated)</label>
                        <input type="text" class="crm-input" wire:model="optionsInput" placeholder="Fintech, Healthcare">
                        @error('optionsInput') <div class="crm-error">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="crm-form-group">
                    <label class="crm-label">Order Index</label>
                    <input type="number" class="crm-input" wire:model="orderIndex" min="0">
                </div>
                <div class="crm-form-group" style="display:flex; align-items:flex-end;">
                    <label class="crm-flex">
                        <input type="checkbox" wire:model="isRequired">
                        <span>Required</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="crm-btn crm-btn-primary">Add Custom Field</button>
        </form>

        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Options</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($definitions as $def)
                        <tr>
                            <td><code>{{ $def->field_key }}</code></td>
                            <td>{{ $def->label }}</td>
                            <td>{{ $def->data_type }}</td>
                            <td>{{ $def->is_required ? 'Yes' : 'No' }}</td>
                            <td>{{ $def->options ? implode(', ', $def->normalizedOptions()) : '—' }}</td>
                            <td>
                                <button
                                    type="button"
                                    wire:click="deleteField({{ $def->id }})"
                                    class="crm-btn crm-btn-danger crm-btn-sm"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="crm-muted">No custom fields defined for {{ $entityType }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
