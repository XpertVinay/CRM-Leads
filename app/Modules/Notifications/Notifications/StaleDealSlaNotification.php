<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Pipeline\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class StaleDealSlaNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Deal $deal,
    ) {
    }

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
            ->subject('SLA Warning — Stale Deal: ' . $this->deal->title)
            ->line("Deal \"{$this->deal->title}\" has exceeded its stage SLA threshold.")
            ->action('Review Deal', url('/deals/' . $this->deal->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Stale Deal SLA Alert',
            'message' => "Deal \"{$this->deal->title}\" has been flagged as stale.",
            'deal_id' => $this->deal->id,
            'organization_id' => $this->deal->organization_id,
            'url' => '/deals/' . $this->deal->id,
        ];
    }
}
