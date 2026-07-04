<?php

namespace App\Notifications;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MaintenanceRequest $request,
        public ?MaintenanceStatus $oldStatus,
        public MaintenanceStatus $newStatus,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Maintenance request updated'))
            ->line(__('Status changed to :status.', ['status' => $this->newStatus->getLabel()]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'maintenance',
            'event' => 'maintenance_status_changed',
            'maintenance_request_id' => $this->request->id,
            'old_status' => $this->oldStatus?->value,
            'new_status' => $this->newStatus->value,
            'title' => __('Maintenance request updated'),
            'body' => __('Status changed to :status.', ['status' => $this->newStatus->getLabel()]),
        ];
    }
}
