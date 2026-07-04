<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueNotification;
use App\Support\Notifications\NotificationDeduplicator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyOverdueInvoices extends Command
{
    protected $signature = 'invoices:notify-overdue';

    protected $description = 'Send daily tenant notifications for overdue invoices without duplicates';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        Invoice::withoutGlobalScopes()
            ->with('tenant')
            ->whereIn('payment_status', [
                InvoiceStatus::Pending->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereDate('due_date', '<', $today)
            ->whereRaw('amount_due > amount_paid')
            ->chunkById(100, function ($invoices) use ($today, &$sent) {
                foreach ($invoices as $invoice) {
                    if (! $invoice->tenant) {
                        continue;
                    }

                    $invoice->recalculateFromLedger();
                    $invoice->refresh();

                    if (in_array($invoice->payment_status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true)) {
                        continue;
                    }

                    $wasSent = NotificationDeduplicator::sendOnce(
                        $invoice->tenant,
                        new InvoiceOverdueNotification($invoice, $today->toDateString()),
                        [
                            'invoice_id' => $invoice->id,
                            'reminder_date' => $today->toDateString(),
                        ],
                    );

                    $sent += $wasSent ? 1 : 0;
                }
            });

        $this->info("Sent {$sent} overdue invoice notification(s).");

        return self::SUCCESS;
    }
}
