<div>
    <div class="crm-form-group">
        <label class="crm-label">Approved Template</label>
        <select wire:model.live="selectedTemplateId" class="crm-select">
            <option value="">-- Choose template --</option>
            @foreach($templates as $tpl)
                <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->language }})</option>
            @endforeach
        </select>
    </div>

    @if($selected)
        <div class="crm-alert crm-alert-success">
            <strong>Preview:</strong> {{ $selected->body_text }}
        </div>
        @foreach($selected->parameter_placeholders ?? [] as $idx => $placeholder)
            <div class="crm-form-group">
                <label class="crm-label">Parameter {{ $idx + 1 }} ({{ $placeholder }})</label>
                <input type="text" wire:model="parameters.{{ $idx }}" class="crm-input">
            </div>
        @endforeach
    @endif
</div>
