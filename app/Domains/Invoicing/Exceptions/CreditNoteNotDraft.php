<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Invoicing\Models\CreditNote;
use RuntimeException;

class CreditNoteNotDraft extends RuntimeException
{
    public static function for(CreditNote $creditNote): self
    {
        return new self("Credit note [{$creditNote->id}] is not a draft.");
    }
}
