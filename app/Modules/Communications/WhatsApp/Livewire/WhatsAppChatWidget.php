<?php

namespace App\Modules\Communications\WhatsApp\Livewire;

use App\Modules\Communications\WhatsApp\Adapters\MetaGraphApiClient;
use App\Modules\Communications\WhatsApp\Jobs\SendWhatsAppTemplateJob;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Communications\WhatsApp\Services\ConsentService;
use App\Modules\Communications\WhatsApp\Services\WindowEnforcementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class WhatsAppChatWidget extends Component
{
    public ?int $conversationId = null;

    public string $messageBody = '';

    public ?int $selectedTemplateId = null;

    public array $templateParams = [];

    public function mount(?int $conversationId = null): void
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        if ($conversationId) {
            $this->conversationId = $conversationId;
        } else {
            $this->conversationId = WhatsAppConversation::orderByDesc('last_message_at')->value('id');
        }
    }

    public function selectConversation(int $id): void
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        $this->conversationId = $id;
    }

    public function sendText(): void
    {
        $this->validate(['messageBody' => ['required', 'string', 'max:4096']]);

        $conversation = WhatsAppConversation::findOrFail($this->conversationId);
        Gate::authorize('sendMessage', $conversation);

        app(WindowEnforcementService::class)->assertCanSendFreeFormText($conversation);
        app(ConsentService::class)->assertOptedIn((int) $conversation->organization_id, (int) $conversation->contact_id);

        $account = $conversation->account;
        if (!$account || !$account->is_active) {
            $this->addError('messageBody', 'WhatsApp account is not active.');

            return;
        }
        $result = app(MetaGraphApiClient::class)->sendTextMessage(
            $account->phone_number_id,
            $account->getPlainAccessToken(),
            $conversation->customer_phone,
            $this->messageBody,
        );

        WhatsAppMessage::create([
            'organization_id' => $conversation->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $this->messageBody,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $this->messageBody = '';
    }

    public function sendTemplate(): void
    {
        $this->validate([
            'selectedTemplateId' => ['required', 'integer'],
            'templateParams' => ['array', 'max:20'],
            'templateParams.*' => ['string', 'max:1024'],
        ]);

        $conversation = WhatsAppConversation::findOrFail($this->conversationId);
        Gate::authorize('sendMessage', $conversation);

        app(ConsentService::class)->assertOptedIn((int) $conversation->organization_id, (int) $conversation->contact_id);

        $template = WhatsAppTemplate::where('meta_status', 'APPROVED')->findOrFail($this->selectedTemplateId);

        $message = WhatsAppMessage::create([
            'organization_id' => $conversation->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body' => $template->body_text,
            'template_name' => $template->name,
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        SendWhatsAppTemplateJob::dispatch(
            $message->id,
            $conversation->id,
            $template->id,
            array_values($this->templateParams),
        );

        $this->selectedTemplateId = null;
        $this->templateParams = [];
    }

    public function render()
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        $conversations = WhatsAppConversation::with('contact:id,first_name,last_name')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $activeConversation = $this->conversationId
            ? WhatsAppConversation::with(['contact', 'messages'])->find($this->conversationId)
            : null;

        $approvedTemplates = WhatsAppTemplate::where('meta_status', 'APPROVED')
            ->orderBy('name')
            ->get();

        return view('livewire.whatsapp.chat-widget', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'approvedTemplates' => $approvedTemplates,
        ]);
    }
}
