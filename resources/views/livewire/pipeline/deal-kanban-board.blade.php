<div>
    <div class="crm-toolbar" style="margin-bottom:1rem;">
        <label for="kanban-pipeline-select">{{ __('Pipelines') }}:</label>
        <select id="kanban-pipeline-select" wire:model.live="pipelineId" class="crm-input" style="max-width:250px;">
            @foreach($pipelines as $p)
                <option value="{{ $p->id }}">{{ $p->name }}{{ $p->is_default ? ' (Default)' : '' }}</option>
            @endforeach
        </select>
    </div>

    @if($pipeline)
        <div class="crm-kanban-board">
            @foreach($pipeline->stages as $stage)
                <div class="crm-kanban-column"
                     data-stage-id="{{ $stage->id }}"
                     ondragover="event.preventDefault(); this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="CRM.kanbanDrop(event, {{ $stage->id }})">

                    <div class="crm-kanban-header crm-kanban-{{ $stage->category }}">
                        <span>{{ $stage->name }}</span>
                        <span class="crm-badge">{{ $stage->deals->count() }}</span>
                    </div>

                    <div class="crm-kanban-cards">
                        @foreach($stage->deals as $deal)
                            <div class="crm-kanban-card {{ $deal->is_stale ? 'crm-stale' : '' }}"
                                 draggable="true"
                                 data-deal-id="{{ $deal->id }}"
                                 ondragstart="event.dataTransfer.setData('text/plain', '{{ $deal->id }}')">
                                <a href="{{ route('deals.show', $deal) }}" class="crm-kanban-card-title">{{ $deal->title }}</a>
                                <div class="crm-kanban-card-meta">
                                    <span>${{ number_format((float) $deal->amount, 2) }}</span>
                                    @if($deal->contact)
                                        <span>{{ $deal->contact->fullName() }}</span>
                                    @endif
                                </div>
                                @if($deal->assignedUser)
                                    <div class="crm-kanban-card-owner">{{ $deal->assignedUser->name }}</div>
                                @endif
                                @if($deal->is_stale)
                                    <div class="crm-kanban-card-stale">⚠ Stale</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Lost Reason Modal -->
        <div id="lost-reason-modal" class="crm-modal-backdrop">
            <div class="crm-modal">
                <div class="crm-modal-header">
                    <h2>Lost Reason Required</h2>
                    <button class="crm-modal-close" onclick="CRM.closeModal('lost-reason-modal')">&times;</button>
                </div>
                <div class="crm-form-group">
                    <label for="lost-reason-input">Why was this deal lost? *</label>
                    <textarea id="lost-reason-input" class="crm-input" rows="3" required></textarea>
                </div>
                <div class="crm-modal-footer">
                    <button class="crm-btn crm-btn-secondary" onclick="CRM.closeModal('lost-reason-modal')">Cancel</button>
                    <button class="crm-btn crm-btn-primary" onclick="CRM.confirmLostMove()">Confirm</button>
                </div>
            </div>
        </div>
    @else
        <p class="crm-muted">No pipelines configured. <a href="{{ route('pipelines.index') }}">Create one →</a></p>
    @endif
</div>

@push('scripts')
<script>
CRM._pendingKanbanMove = null;

CRM.kanbanDrop = function(event, stageId) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    var dealId = parseInt(event.dataTransfer.getData('text/plain'));
    var col = event.currentTarget;
    var isLost = col.querySelector('.crm-kanban-header').classList.contains('crm-kanban-lost');

    if (isLost) {
        CRM._pendingKanbanMove = { dealId: dealId, stageId: stageId };
        CRM.modal('lost-reason-modal');
    } else {
        @this.call('moveToStage', dealId, stageId, null);
    }
};

CRM.confirmLostMove = function() {
    var reason = document.getElementById('lost-reason-input').value.trim();
    if (!reason) { alert('Please provide a reason.'); return; }
    @this.call('moveToStage', CRM._pendingKanbanMove.dealId, CRM._pendingKanbanMove.stageId, reason);
    CRM.closeModal('lost-reason-modal');
    document.getElementById('lost-reason-input').value = '';
    CRM._pendingKanbanMove = null;
};
</script>
@endpush
