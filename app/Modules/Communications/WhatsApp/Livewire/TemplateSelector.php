<?php

namespace App\Modules\Communications\WhatsApp\Livewire;

use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TemplateSelector extends Component
{
    public ?int $selectedTemplateId = null;

    public array $parameters = [];

    public function render()
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        $templates = WhatsAppTemplate::where('meta_status', 'APPROVED')
            ->orderBy('name')
            ->get();

        $selected = $this->selectedTemplateId
            ? $templates->firstWhere('id', $this->selectedTemplateId)
            : null;

        return view('livewire.whatsapp.template-selector', [
            'templates' => $templates,
            'selected' => $selected,
        ]);
    }
}
