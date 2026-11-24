<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Invoicing\Models\Invoice;
use RuntimeException;

class InvoiceNotIssuable extends RuntimeException
{
    public static function for(Invoice $invoice, string $reason): self
    {
        return new self("Invoice [{$invoice->id}] cannot be issued: {$reason}");
    }
}
