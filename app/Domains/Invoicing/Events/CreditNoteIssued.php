<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\CreditNote;

class CreditNoteIssued
{
    public function __construct(
        public readonly CreditNote $creditNote,
    ) {}
}
