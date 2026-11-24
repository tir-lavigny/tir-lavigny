<?php

namespace App\Domains\Invoicing\Enums;

enum CreditNoteReversalStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
}
