<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InboundWhatsAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly WhatsAppMessage $message,
        public readonly string $customerPhone,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New WhatsApp Message from '.$this->customerPhone)
            ->line((string) ($this->message->body ?? 'New inbound message received.'))
            ->action('Open WhatsApp Inbox', url('/whatsapp/chat'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Inbound WhatsApp Message',
            'message' => "New message from {$this->customerPhone}: ".($this->message->body ?? ''),
            'whatsapp_conversation_id' => $this->message->whatsapp_conversation_id,
            'organization_id' => $this->message->organization_id,
            'url' => '/whatsapp/chat',
        ];
    }
}
