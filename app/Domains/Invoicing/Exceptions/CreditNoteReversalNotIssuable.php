<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Invoicing\Models\CreditNoteReversal;
use RuntimeException;

class CreditNoteReversalNotIssuable extends RuntimeException
{
    public static function for(CreditNoteReversal $reversal, string $reason): self
    {
        return new self("Credit note reversal [{$reversal->id}] cannot be issued: {$reason}");
    }
}
