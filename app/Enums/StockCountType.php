<?php

namespace App\Enums;

enum StockCountType: string
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case Final = 'final';
}
