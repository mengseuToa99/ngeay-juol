<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionAccessChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $accessState) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return match ($this->accessState) {
            'read_only' => (new MailMessage)
                ->subject(__('Subscription read-only access'))
                ->line(__('Your subscription is now read-only while payment is pending.'))
                ->line(__('Please complete payment to restore full access.')),
            'revoked' => (new MailMessage)
                ->subject(__('Subscription access revoked'))
                ->line(__('Your subscription access has been revoked because the retention period ended.')),
            default => (new MailMessage)
                ->subject(__('Subscription access updated'))
                ->line(__('Your subscription access status changed.')),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'subscription',
            'event' => 'subscription_access_changed',
            'subscription_id' => $this->subscription->id,
            'landlord_id' => $this->subscription->landlord_id,
            'access_state' => $this->accessState,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'title' => match ($this->accessState) {
                'read_only' => __('Subscription read-only access'),
                'revoked' => __('Subscription access revoked'),
                default => __('Subscription access updated'),
            },
            'body' => match ($this->accessState) {
                'read_only' => __('Your subscription is now read-only while payment is pending.'),
                'revoked' => __('Your subscription access has been revoked because the retention period ended.'),
                default => __('Your subscription access status changed.'),
            },
        ];
    }
}
