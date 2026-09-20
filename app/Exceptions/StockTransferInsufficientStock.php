<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class StockTransferInsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            "Insufficient stock for product \"{$product->name}\": available {$available}, requested {$requested}."
        );
    }
}
