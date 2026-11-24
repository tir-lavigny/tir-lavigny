<?php

namespace App\Domains\Invoicing\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';
}
