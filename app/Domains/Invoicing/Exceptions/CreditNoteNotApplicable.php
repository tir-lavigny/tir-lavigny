<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Invoicing\Models\CreditNote;
use RuntimeException;

class CreditNoteNotApplicable extends RuntimeException
{
    public static function for(CreditNote $creditNote, string $reason): self
    {
        return new self("Credit note [{$creditNote->id}] cannot be applied: {$reason}");
    }
}
