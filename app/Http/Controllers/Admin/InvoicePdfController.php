<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): StreamedResponse
    {
        abort_unless($invoice->pdf_disk === 'local' && filled($invoice->pdf_path) && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path, 'invoice-'.$invoice->invoice_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
