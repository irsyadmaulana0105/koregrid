<?php

namespace App\Application\Ordering\DTOs;

final readonly class OrderLineData
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public int $quantity,
    ) {}
}
