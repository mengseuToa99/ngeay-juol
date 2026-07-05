<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public string $reminderStage = 'renewal_pending',
        public ?string $coversFrom = null,
        public ?string $coversTo = null,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Subscription renewal pending payment'))
            ->line(__('Your subscription renewal is pending payment.'))
            ->line(__('Coverage runs from :from to :to.', [
                'from' => $this->coversFrom ?? __('not set'),
                'to' => $this->coversTo ?? __('not set'),
            ]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'subscription',
            'event' => 'subscription_renewal_pending',
            'subscription_id' => $this->subscription->id,
            'landlord_id' => $this->subscription->landlord_id,
            'reminder_stage' => $this->reminderStage,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'title' => __('Subscription renewal pending payment'),
            'body' => __('Your subscription renewal is pending payment.'),
        ];
    }
}
