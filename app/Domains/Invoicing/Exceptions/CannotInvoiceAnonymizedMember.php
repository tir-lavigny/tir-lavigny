<?php

namespace App\Domains\Invoicing\Exceptions;

use RuntimeException;

class CannotInvoiceAnonymizedMember extends RuntimeException
{
    public static function forMember(int $memberId): self
    {
        return new self("Member [{$memberId}] is anonymized and cannot be invoiced.");
    }
}
