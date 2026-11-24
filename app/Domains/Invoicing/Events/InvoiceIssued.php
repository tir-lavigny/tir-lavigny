<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;

class InvoiceIssued
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
