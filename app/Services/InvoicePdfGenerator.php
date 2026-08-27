<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePdfReady;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Support\Facades\Storage;

final class InvoicePdfGenerator
{
    public function __construct(private PersianPdfHtml $persian) {}

    public function generate(Invoice $invoice): Invoice
    {
        $invoice->load('items');
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true)) {
            throw new DomainException('invoice_pdf_unavailable');
        }
        $html = view('invoices.pdf', ['invoice' => $invoice])->render();
        $bytes = Pdf::loadHTML($this->persian->shape($html))->setPaper('a4')->output();
        $path = 'invoices/invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice->invoice_number).'.pdf';
        Storage::disk('local')->put($path, $bytes);
        $invoice->forceFill(['pdf_disk' => 'local', 'pdf_path' => $path, 'pdf_hash' => hash('sha256', $bytes), 'pdf_status' => 'ready', 'pdf_error' => null, 'pdf_generated_at' => now()])->save();
        event(new InvoicePdfReady($invoice->fresh()));

        return $invoice->fresh();
    }
}
