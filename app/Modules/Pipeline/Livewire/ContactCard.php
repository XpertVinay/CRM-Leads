<?php

namespace App\Modules\Pipeline\Livewire;

use App\Modules\Pipeline\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ContactCard extends Component
{
    public ?int $contactId = null;

    public function mount(?int $contactId = null): void
    {
        $this->contactId = $contactId;
    }

    public function render()
    {
        $contact = null;
        if ($this->contactId) {
            $contact = Contact::with(['account', 'deals.pipelineStage'])->find($this->contactId);
            if ($contact) {
                Gate::authorize('view', $contact);
            }
        }

        return view('livewire.pipeline.contact-card', [
            'contact' => $contact,
        ]);
    }
}
