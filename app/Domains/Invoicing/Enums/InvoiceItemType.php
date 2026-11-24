<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceItemType: string
{
    case Product = 'product';
    case Fee = 'fee';
    case Discount = 'discount';
    case Adjustment = 'adjustment';
}
