<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPastDueNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $reminderStage = 'past_due') {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Subscription past due'))
            ->line(__('Your subscription is past due.'))
            ->line(__('Grace period ends on :date.', [
                'date' => $this->subscription->grace_ends_at?->toFormattedDateString() ?? __('not set'),
            ]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'subscription',
            'event' => 'subscription_past_due',
            'subscription_id' => $this->subscription->id,
            'landlord_id' => $this->subscription->landlord_id,
            'reminder_stage' => $this->reminderStage,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'grace_ends_at' => $this->subscription->grace_ends_at?->toDateString(),
            'title' => __('Subscription past due'),
            'body' => __('Your subscription is past due.'),
        ];
    }
}
