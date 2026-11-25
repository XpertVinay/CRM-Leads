<div>
    @if($lead !== null)
        <div class="crm-modal-backdrop open">
            <div class="crm-modal">
                <div class="crm-card-header">
                    <h3 class="crm-card-title">Convert {{ $lead->type }} Lead — {{ $lead->fullName() }}</h3>
                    <button type="button" wire:click="closeWizard" class="crm-btn crm-btn-secondary crm-btn-sm">Close</button>
                </div>

                @if($statusMessage)
                    <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
                @endif

                <form wire:submit="convertLead">
                    @if($lead->isB2B())
                        <div class="crm-form-group">
                            <label class="crm-label">Existing Account (optional)</label>
                            <select class="crm-select" wire:model="accountId">
                                <option value="">— Create or Match by Name —</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Account Name</label>
                            <input type="text" class="crm-input" wire:model="accountName">
                            @error('accountName') <div class="crm-error">{{ $message }}</div> @enderror
                        </div>
                    @else
                        <div class="crm-alert crm-alert-warning">
                            B2C Mode: Converts to a standalone Contact (no Account).
                        </div>
                    @endif

                    <div class="crm-form-group">
                        <label class="crm-flex">
                            <input type="checkbox" wire:model.live="createDeal">
                            <span>Create Deal upon conversion</span>
                        </label>
                    </div>

                    @if($createDeal)
                        <div class="crm-grid crm-grid-2">
                            <div class="crm-form-group">
                                <label class="crm-label">Deal Title</label>
                                <input type="text" class="crm-input" wire:model="dealTitle">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Deal Amount</label>
                                <input type="number" step="0.01" min="0" class="crm-input" wire:model="dealAmount">
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Pipeline</label>
                                <select class="crm-select" wire:model.live="pipelineId">
                                    @foreach($pipelines as $pipeline)
                                        <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="crm-btn crm-btn-primary">Confirm Conversion</button>
                </form>
            </div>
        </div>
    @endif
</div>
