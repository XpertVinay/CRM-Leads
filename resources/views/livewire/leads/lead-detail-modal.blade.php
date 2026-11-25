<div>
    @if($lead !== null)
        <div class="crm-modal-backdrop open">
            <div class="crm-modal">
                <div class="crm-card-header">
                    <h3 class="crm-card-title">Lead Detail — {{ $lead->fullName() }} ({{ $lead->type }})</h3>
                    <button type="button" wire:click="closeModal" class="crm-btn crm-btn-secondary crm-btn-sm">Close</button>
                </div>

                @if($statusMessage)
                    <div class="crm-alert crm-alert-success">{{ $statusMessage }}</div>
                @endif

                <form wire:submit="saveLead">
                    <div class="crm-grid crm-grid-2">
                        <div class="crm-form-group">
                            <label class="crm-label">First Name</label>
                            <input type="text" class="crm-input" wire:model="firstName">
                            @error('firstName') <div class="crm-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Last Name</label>
                            <input type="text" class="crm-input" wire:model="lastName">
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Email</label>
                            <input type="email" class="crm-input" wire:model="email">
                        </div>
                        <div class="crm-form-group">
                            <label class="crm-label">Phone</label>
                            <input type="text" class="crm-input" wire:model="phone">
                        </div>
                        @if($lead->isB2B())
                            <div class="crm-form-group">
                                <label class="crm-label">Company Name</label>
                                <input type="text" class="crm-input" wire:model="companyName">
                                @error('companyName') <div class="crm-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="crm-form-group">
                                <label class="crm-label">Job Title</label>
                                <input type="text" class="crm-input" wire:model="jobTitle">
                            </div>
                        @else
                            <div class="crm-form-group">
                                <label class="crm-label">Location</label>
                                <input type="text" class="crm-input" wire:model="location">
                            </div>
                        @endif
                        <div class="crm-form-group">
                            <label class="crm-label">Status</label>
                            <select class="crm-select" wire:model="status">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="unqualified">Unqualified</option>
                            </select>
                        </div>
                    </div>

                    @can('update', $lead)
                        @if(! $lead->isConverted())
                            <button type="submit" class="crm-btn crm-btn-primary">Save Changes</button>
                        @endif
                    @endcan
                </form>
            </div>
        </div>
    @endif
</div>
