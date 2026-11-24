<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;

class InvoiceCancelled
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
