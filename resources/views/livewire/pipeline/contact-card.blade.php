<div>
    @if($contact)
        <div class="crm-card">
            <h3>{{ $contact->fullName() }}</h3>
            <dl class="crm-dl">
                <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
                <dt>Phone</dt><dd>{{ $contact->phone ?? '—' }}</dd>
                <dt>Job Title</dt><dd>{{ $contact->job_title ?? '—' }}</dd>
                <dt>Preferred Channel</dt><dd>{{ $contact->preferred_channel ?? 'email' }}</dd>
                @if($contact->account)
                    <dt>Account</dt><dd>{{ $contact->account->name }}</dd>
                @endif
            </dl>

            @if($contact->deals->isNotEmpty())
                <h4>Deals</h4>
                @foreach($contact->deals as $deal)
                    <div class="crm-list-item">
                        <span>{{ $deal->title }}</span>
                        <span class="crm-badge crm-badge-{{ $deal->pipelineStage?->category ?? 'open' }}">
                            {{ $deal->pipelineStage?->name ?? 'N/A' }}
                        </span>
                    </div>
                @endforeach
            @endif
        </div>
    @else
        <p class="crm-muted">Select a contact to view details.</p>
    @endif
</div>
