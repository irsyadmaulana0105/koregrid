<?php

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class InsufficientStockException extends DomainException
{
    public static function for(int $productId, int $warehouseId, int $requested): self
    {
        return new self("Stok tidak cukup: produk #{$productId} di gudang #{$warehouseId}, diminta {$requested}.");
    }
}
