<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Transfer = 'transfer';
    case Loss = 'loss';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}