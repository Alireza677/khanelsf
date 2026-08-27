<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\ClientInvoiceAccess;
use App\Services\PublicAccountNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, ClientInvoiceAccess $access, PublicAccountNavigation $navigation): View
    {
        return view('client.invoices.index', ['invoices' => $access->paginate($request->user('client')), 'accountNavigation' => $navigation->present()]);
    }

    public function show(Request $request, Invoice $invoice, ClientInvoiceAccess $access, PublicAccountNavigation $navigation): View
    {
        return view('client.invoices.show', ['invoice' => $access->find($request->user('client'), $invoice->getKey()), 'accountNavigation' => $navigation->present()]);
    }

    public function download(Request $request, Invoice $invoice, ClientInvoiceAccess $access): StreamedResponse
    {
        $invoice = $access->find($request->user('client'), $invoice->getKey());
        abort_unless($invoice->pdf_disk === 'local' && filled($invoice->pdf_path) && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path, 'invoice-'.$invoice->invoice_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
