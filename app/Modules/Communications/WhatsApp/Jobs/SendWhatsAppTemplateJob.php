<?php

namespace App\Modules\Communications\WhatsApp\Jobs;

use App\Modules\Communications\WhatsApp\Adapters\MetaGraphApiClient;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppTemplateJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $parameters
     */
    public function __construct(
        public readonly int $messageId,
        public readonly int $conversationId,
        public readonly int $templateId,
        public readonly array $parameters = [],
    ) {}

    public function handle(MetaGraphApiClient $client): void
    {
        $message = WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->find($this->messageId);
        $conversation = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)->find($this->conversationId);
        $template = WhatsAppTemplate::withoutGlobalScope(OrganizationScope::class)->find($this->templateId);

        if (! $message || ! $conversation || ! $template) {
            return;
        }

        $account = WhatsAppAccount::withoutGlobalScope(OrganizationScope::class)->find($conversation->whatsapp_account_id);
        if (! $account) {
            return;
        }

        try {
            $result = $client->sendTemplateMessage(
                $account->phone_number_id,
                $account->getPlainAccessToken(),
                $conversation->customer_phone,
                $template->name,
                $template->language,
                $this->parameters,
            );

            $wamid = $result['messages'][0]['id'] ?? null;

            $message->update([
                'meta_message_id' => $wamid,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $conversation->update(['last_message_at' => now()]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_payload' => ['message' => $e->getMessage()],
            ]);
        }
    }
}
