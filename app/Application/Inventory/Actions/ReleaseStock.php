<?php

namespace App\Application\Inventory\Actions;

use App\Domain\Inventory\Exceptions\StockInvariantViolationException;
use App\Domain\Inventory\Models\Inventory;
use InvalidArgumentException;

/**
 * Melepas reservasi (PO dibatalkan, ditolak, atau reservasinya kedaluwarsa).
 */
final class ReleaseStock
{
    public function __invoke(int $productId, int $warehouseId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah pelepasan harus minimal 1.');
        }

        $updated = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_reserved', '>=', $quantity)
            ->decrement('qty_reserved', $quantity);

        if ($updated === 0) {
            throw StockInvariantViolationException::nothingToRelease($productId, $warehouseId, $quantity);
        }
    }
}
