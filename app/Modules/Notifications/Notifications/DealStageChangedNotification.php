<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Pipeline\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DealStageChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Deal $deal,
        public readonly string $oldStageName,
        public readonly string $newStageName,
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
            ->subject('Deal Stage Updated: '.$this->deal->title)
            ->line("Deal \"{$this->deal->title}\" moved from {$this->oldStageName} to {$this->newStageName}.")
            ->action('View Deal', url('/deals/'.$this->deal->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Deal Stage Changed',
            'message' => "Deal \"{$this->deal->title}\" moved from {$this->oldStageName} to {$this->newStageName}.",
            'deal_id' => $this->deal->id,
            'organization_id' => $this->deal->organization_id,
            'url' => '/deals/'.$this->deal->id,
        ];
    }
}
