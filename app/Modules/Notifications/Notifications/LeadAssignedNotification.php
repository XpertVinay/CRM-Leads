<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Leads\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
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
            ->subject('New Lead Assigned: ' . $this->lead->fullName())
            ->line('A lead has been assigned to you: ' . $this->lead->fullName())
            ->line('Type: ' . $this->lead->type . ' | Source: ' . $this->lead->source)
            ->action('View Lead', url('/leads/' . $this->lead->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Lead Assigned',
            'message' => "Lead {$this->lead->fullName()} was assigned to you.",
            'lead_id' => $this->lead->id,
            'organization_id' => $this->lead->organization_id,
            'url' => '/leads/' . $this->lead->id,
        ];
    }
}
