<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

class InvoiceNumberSequenceExhausted extends RuntimeException
{
    public static function forYear(int $year): self
    {
        return new self("Invoice number sequence for {$year} is exhausted.");
    }
}
