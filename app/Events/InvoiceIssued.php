<?php

namespace App\Events;

use App\Models\Invoice;

final class InvoiceIssued
{
    public function __construct(public readonly Invoice $invoice) {}
}
