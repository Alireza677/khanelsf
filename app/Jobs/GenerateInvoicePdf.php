<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoicePdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public int $invoiceId)
    {
        $this->afterCommit();
    }

    public function handle(InvoicePdfGenerator $g): void
    {
        $g->generate(Invoice::findOrFail($this->invoiceId));
    }

    public function failed(Throwable $e): void
    {
        Invoice::whereKey($this->invoiceId)->update(['pdf_status' => 'failed', 'pdf_error' => mb_substr($e->getMessage(), 0, 255)]);
    }
}
