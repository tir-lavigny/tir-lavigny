<?php

namespace App\Domains\Invoicing\Enums;

enum CustomerAccountEntryType: string
{
    case InvoiceIssued = 'invoice_issued';
    case InvoiceCancelled = 'invoice_cancelled';
    case PaymentAllocated = 'payment_allocated';
    case CreditNoteIssued = 'credit_note_issued';
    case CreditApplied = 'credit_applied';
    case CreditApplicationReversed = 'credit_application_reversed';
    case CreditNoteRefunded = 'credit_note_refunded';
    case CreditNoteReversed = 'credit_note_reversed';
}
