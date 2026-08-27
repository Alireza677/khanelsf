<?php

namespace App\Events;

use App\Models\Invoice;

final class InvoicePdfReady
{
    public function __construct(public readonly Invoice $invoice) {}
}
