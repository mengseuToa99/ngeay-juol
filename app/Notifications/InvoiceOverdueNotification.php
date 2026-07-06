<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice, public string $reminderDate) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Invoice :number is overdue', ['number' => $this->invoice->invoice_number]))
            ->line(__('This invoice is now overdue.'))
            ->line(__('Outstanding balance: :amount', ['amount' => Money::formatForRecord($this->invoice->balance, $this->invoice)]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'invoice',
            'event' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'reminder_date' => $this->reminderDate,
            'balance' => (float) $this->invoice->balance,
            'title' => __('Invoice overdue'),
            'body' => __('Invoice :number has an outstanding balance of :amount.', [
                'number' => $this->invoice->invoice_number,
                'amount' => Money::formatForRecord($this->invoice->balance, $this->invoice),
            ]),
        ];
    }
}
