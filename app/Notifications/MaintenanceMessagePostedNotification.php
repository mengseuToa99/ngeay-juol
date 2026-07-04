<?php

namespace App\Notifications;

use App\Models\MaintenanceMessage;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MaintenanceMessagePostedNotification extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceMessage $message) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New maintenance message'))
            ->line(Str::limit($this->message->body, 140));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'maintenance',
            'event' => 'maintenance_message_posted',
            'maintenance_message_id' => $this->message->id,
            'maintenance_request_id' => $this->message->request_id,
            'sender_id' => $this->message->sender_id,
            'title' => __('New maintenance message'),
            'body' => Str::limit($this->message->body, 140),
        ];
    }
}
