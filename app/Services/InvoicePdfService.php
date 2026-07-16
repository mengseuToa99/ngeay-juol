<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\InvoicePaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renders an invoice to a PDF document using headless Chrome (Browsershot) at a chosen paper size.
 *
 * The same Blade view drives both the standard ISO-page layout and the narrow
 * thermal-receipt layout; {@see InvoicePaper} decides which one and supplies the
 * paper geometry.
 */
class InvoicePdfService
{
    /** Build a print-ready PDF for the invoice at the requested paper size. */
    public function make(Invoice $invoice, string $size): string
    {
        $invoice->loadMissing(['lines', 'payments.recordedBy', 'tenant', 'rental.unit.property', 'property']);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'size' => $size,
            'thermal' => InvoicePaper::isThermal($size),
        ])->render();

        $browsershot = Browsershot::html($html)
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->setNodeModulePath(config('services.browsershot.node_module_path', base_path('node_modules')))
            ->noSandbox();
            
        if ($chromePath = config('services.browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot->addChromiumArguments(config('services.browsershot.chromium_arguments', []));
        $browsershot->addChromiumArguments(['allow-file-access-from-files']);

        if ($nodeBinary = $this->nodeBinary()) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = config('services.browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($includePath = config('services.browsershot.include_path')) {
            $browsershot->setIncludePath($includePath);
        }

        if ($size === 'a4') {
            $browsershot->format('A4');
        } elseif ($size === 'a5') {
            $browsershot->format('A5');
        } else {
            // Thermal receipt: pass exact mm dimensions to Puppeteer
            $browsershot->paperWidth(80, 'mm')
                ->paperHeight(220, 'mm');
        }

        try {
            return $browsershot->pdf();
        } catch (Throwable $exception) {
            Log::warning('Browsershot invoice PDF render failed; falling back to dompdf.', [
                'invoice_id' => $invoice->getKey(),
                'size' => $size,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->makeWithDompdf($invoice, $size);
        }
    }

    /** Build the same invoice PDF with dompdf when Chromium is unavailable. */
    protected function makeWithDompdf(Invoice $invoice, string $size): string
    {
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'size' => $size,
            'thermal' => InvoicePaper::isThermal($size),
        ]);

        $pdf->setPaper(InvoicePaper::dompdfPaper($size, $invoice), 'portrait');

        return $pdf->output();
    }

    protected function nodeBinary(): ?string
    {
        $configured = config('services.browsershot.node_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $playwrightNodes = glob((string) getenv('HOME') . '/.cache/ms-playwright-go/*/node') ?: [];

        usort($playwrightNodes, 'strnatcmp');

        foreach (array_reverse($playwrightNodes) as $node) {
            if (is_executable($node)) {
                return $node;
            }
        }

        return null;
    }

    /** Safe download filename for an invoice document (sanitised invoice number). */
    public static function filename(Invoice $invoice, string $ext): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $invoice->invoice_number);

        return $base . '.' . $ext;
    }
}
