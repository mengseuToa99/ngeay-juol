<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New maintenance request'))
            ->line($this->request->title);
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'maintenance',
            'event' => 'maintenance_request_created',
            'maintenance_request_id' => $this->request->id,
            'tenant_id' => $this->request->tenant_id,
            'property_id' => $this->request->property_id,
            'unit_id' => $this->request->unit_id,
            'title' => __('New maintenance request'),
            'body' => $this->request->title,
        ];
    }
}
