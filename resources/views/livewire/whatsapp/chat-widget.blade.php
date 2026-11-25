<div class="crm-grid" style="grid-template-columns: 280px 1fr;">
    <div class="crm-card">
        <h3>Conversations</h3>
        @forelse($conversations as $conv)
            <div class="crm-list-item" style="cursor:pointer;" wire:click="selectConversation({{ $conv->id }})">
                <div>
                    <strong>{{ $conv->contact?->fullName() ?? $conv->customer_phone }}</strong>
                    <div class="crm-muted">{{ $conv->customer_phone }}</div>
                </div>
                <span class="crm-badge {{ $conv->isWindowActive() ? 'crm-badge-active' : 'crm-badge-warning' }}">
                    {{ $conv->isWindowActive() ? '24h Open' : 'Template Only' }}
                </span>
            </div>
        @empty
            <p class="crm-muted">No conversations yet.</p>
        @endforelse
    </div>

    <div class="crm-card">
        @if($activeConversation)
            <div class="crm-card-header">
                <div>
                    <strong>{{ $activeConversation->contact?->fullName() ?? $activeConversation->customer_phone }}</strong>
                    <span class="crm-muted">{{ $activeConversation->customer_phone }}</span>
                </div>
                <span class="crm-badge {{ $activeConversation->isWindowActive() ? 'crm-badge-active' : 'crm-badge-warning' }}">
                    {{ $activeConversation->isWindowActive() ? '24h Window Active' : '24h Window Expired' }}
                </span>
            </div>

            <div style="max-height:360px; overflow-y:auto; margin-bottom:1rem;">
                @foreach($activeConversation->messages as $msg)
                    <div style="margin-bottom:0.6rem; text-align: {{ $msg->direction === 'outbound' ? 'right' : 'left' }};">
                        <div style="display:inline-block; padding:0.5rem 0.75rem; border-radius:0.5rem; background: {{ $msg->direction === 'outbound' ? '#dbeafe' : '#f3f4f6' }}; max-width:75%; text-align:left;">
                            <div>{{ $msg->body }}</div>
                            <div class="crm-muted" style="font-size:0.7rem;">
                                {{ $msg->message_type }} &middot; {{ $msg->status }}
                                @if($msg->is_coexistence_echo) &middot; Mobile App Echo @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($activeConversation->isWindowActive())
                <form wire:submit.prevent="sendText" class="crm-flex">
                    <input type="text" wire:model="messageBody" class="crm-input" placeholder="Type a message..." style="flex:1;">
                    <button type="submit" class="crm-btn crm-btn-primary">Send</button>
                </form>
            @else
                <div class="crm-alert crm-alert-warning">
                    24-hour window expired. Select an approved template to message this contact.
                </div>
                <form wire:submit.prevent="sendTemplate" class="crm-flex">
                    <select wire:model="selectedTemplateId" class="crm-select" style="flex:1;">
                        <option value="">-- Select Approved Template --</option>
                        @foreach($approvedTemplates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->language }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="crm-btn crm-btn-primary">Send Template</button>
                </form>
            @endif
        @else
            <p class="crm-muted">Select a conversation to view messages.</p>
        @endif
    </div>
</div>
