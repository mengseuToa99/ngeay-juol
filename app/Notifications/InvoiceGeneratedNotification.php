<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use App\Support\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return NotificationChannels::for($notifiable, allowMail: true);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New invoice :number', ['number' => $this->invoice->invoice_number]))
            ->line(__('A new invoice has been generated.'))
            ->line(__('Amount due: :amount', ['amount' => Money::formatInvoiceAmount($this->invoice, 'due')]));
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'invoice',
            'event' => 'invoice_generated',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount_due' => (float) $this->invoice->amount_due,
            'title' => __('New invoice generated'),
            'body' => __('Invoice :number total is :amount.', [
                'number' => $this->invoice->invoice_number,
                'amount' => Money::formatInvoiceAmount($this->invoice, 'due'),
            ]),
        ];
    }
}
