<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Payment recorded'))
            ->line(__('A payment has been recorded for invoice :number.', [
                'number' => $this->payment->invoice?->invoice_number,
            ]))
            ->line(__('Amount: :amount', ['amount' => '$'.number_format((float) $this->payment->amount, 2)]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'payment',
            'event' => 'payment_recorded',
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'invoice_number' => $this->payment->invoice?->invoice_number,
            'amount' => (float) $this->payment->amount,
            'paid_at' => $this->payment->paid_at?->toDateTimeString(),
            'title' => __('Payment recorded'),
            'body' => __('A :amount payment was recorded for invoice :number.', [
                'amount' => '$'.number_format((float) $this->payment->amount, 2),
                'number' => $this->payment->invoice?->invoice_number,
            ]),
        ];
    }
}
