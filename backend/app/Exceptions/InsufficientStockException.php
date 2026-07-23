<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            "Insufficient stock for \"{$productName}\". Available: {$available}, requested: {$requested}."
        );
    }
}
