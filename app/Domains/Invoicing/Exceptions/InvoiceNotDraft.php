<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Invoicing\Models\Invoice;
use RuntimeException;

class InvoiceNotDraft extends RuntimeException
{
    public static function for(Invoice $invoice): self
    {
        return new self("Invoice [{$invoice->id}] is not a draft.");
    }
}
