<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\Money;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceExcelExport
{
    private Invoice $invoice;

    /**
     * Stream the invoice as a downloadable XLSX workbook.
     */
    public function download(Invoice $invoice): StreamedResponse
    {
        $invoice->loadMissing(['lines', 'payments.recordedBy', 'tenant', 'rental.unit.property', 'property']);
        $this->invoice = $invoice;

        return response()->streamDownload(
            fn () => $this->write($invoice),
            InvoicePdfService::filename($invoice, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Write the workbook contents straight to the output buffer.
     */
    private function write(Invoice $invoice): void
    {
        $this->invoice = $invoice;
        $bold = (new Style())->setFontBold();

        $writer = new Writer();
        $writer->openToFile('php://output');

        // --- Title block -------------------------------------------------
        $writer->addRow($this->labelled(__('Invoice'), (string) $invoice->invoice_number, $bold));
        $writer->addRow($this->labelled(__('Property'), (string) optional($invoice->property)->name));
        $writer->addRow($this->labelled(__('Tenant'), (string) (optional($invoice->tenant)->name ?? optional($invoice->rental)->occupant_name)));
        $writer->addRow($this->labelled(__('Room'), (string) optional(optional($invoice->rental)->unit)->room_number));
        $writer->addRow($this->labelled(__('Period'), $this->period($invoice)));
        $writer->addRow($this->labelled(__('Issued'), $this->date($invoice->issue_date)));
        $writer->addRow($this->labelled(__('Due'), $this->date($invoice->due_date)));
        $writer->addRow($this->labelled(__('Status'), (string) optional($invoice->payment_status)->getLabel()));

        $writer->addRow(Row::fromValues([]));

        // --- Line items --------------------------------------------------
        $writer->addRow(Row::fromValues([
            __('Description'),
            __('Type'),
            __('Qty'),
            __('Unit price'),
            __('Currency'),
            __('Amount'),
            __('Charge state'),
            __('State reason'),
            __('Source scope'),
        ], $bold));

        foreach ($invoice->lines->filter(fn ($line) => $line->shouldAppearOnTenantInvoice()) as $line) {
            $writer->addRow(Row::fromValues([
                (string) $line->description,
                (string) optional($line->line_type)->getLabel(),
                (float) $line->quantity,
                (float) $line->unit_price,
                (string) $line->currency,
                (float) ($line->isConcessionState() ? 0 : $line->amount),
                (string) $line->resolvedChargeStateLabel(),
                (string) $line->resolvedChargeStateReason(),
                (string) $line->sourceScopeLabel(),
            ]));
        }

        // --- Totals ------------------------------------------------------
        $writer->addRow(Row::fromValues([]));

        if ($invoice->usd_khr_rate > 0) {
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('USD charges'), (float) $invoice->native_usd_total]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('KHR charges'), (float) $invoice->native_khr_total]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Total USD'), (float) $invoice->total_usd], $bold));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Equivalent KHR'), (float) $invoice->total_khr]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Paid USD'), (float) $invoice->paid_usd]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Paid KHR'), (float) $invoice->paid_khr]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Balance USD'), (float) $invoice->balance_usd], $bold));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Balance KHR'), (float) $invoice->balance_khr], $bold));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Exchange rate'), (float) $invoice->usd_khr_rate]));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Rate source/date'), ($invoice->exchange_rate_source ? __($invoice->exchange_rate_source) : '') . ($invoice->exchange_rate_date ? ' (' . $this->date($invoice->exchange_rate_date) . ')' : '')]));
        } else {
            $writer->addRow($this->total(__('Subtotal'), $invoice->amount_due, $bold));
            $writer->addRow($this->total(__('Total due'), $invoice->amount_due, $bold));
            $writer->addRow($this->total(__('Paid'), $invoice->amount_paid, $bold));
            $writer->addRow($this->total(__('Balance'), $invoice->balance, $bold));
            $writer->addRow(Row::fromValues(['', '', '', '', '', __('Exchange-rate snapshot unavailable'), '']));
        }

        // --- Payments ----------------------------------------------------
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            __('Date'),
            __('Amount'),
            __('Currency'),
            __('USD amount'),
            __('KHR amount'),
            __('Method'),
            __('Receipt'),
        ], $bold));

        foreach ($invoice->payments as $payment) {
            $writer->addRow(Row::fromValues([
                $this->date($payment->paid_at),
                (float) $payment->amount,
                (string) $payment->currency,
                (float) $payment->amount_usd,
                (float) $payment->amount_khr,
                (string) optional($payment->method)->getLabel(),
                (string) $payment->receipt_number,
            ]));
        }

        $writer->close();
    }

    /**
     * A two-column "Label / value" row, optionally styling the label cell.
     */
    private function labelled(string $label, string $value, ?Style $style = null): Row
    {
        return Row::fromValues([$label, $value], $style);
    }

    /**
     * A totals row: blank, blank, blank, label, money.
     */
    private function total(string $label, mixed $amount, Style $style): Row
    {
        return Row::fromValues(['', '', '', '', '', $label, $this->money($amount)], $style);
    }

    /**
     * Format a money value as a "$1,234.56" string.
     */
    private function money(mixed $value): string
    {
        return Money::formatForRecord($value, $this->invoice);
    }

    /**
     * Format a nullable date as "d M Y" (empty string when null).
     */
    private function date(mixed $date): string
    {
        return $date ? $date->format('d M Y') : '';
    }

    /**
     * Render the billing period as "start – end" using available dates.
     */
    private function period(Invoice $invoice): string
    {
        $start = $this->date($invoice->period_start);
        $end = $this->date($invoice->period_end);

        if ('' === $start && '' === $end) {
            return '';
        }

        return trim($start.' – '.$end, ' –');
    }
}
