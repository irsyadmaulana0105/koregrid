<?php

namespace App\Domain\Inventory\Exceptions;

use LogicException;

/** Pembukuan stok tidak konsisten (bug atau data rusak), bukan kesalahan pengguna. */
final class StockInvariantViolationException extends LogicException
{
    public static function nothingToRelease(int $productId, int $warehouseId, int $quantity): self
    {
        return new self("Tidak ada reservasi {$quantity} untuk dilepas: produk #{$productId}, gudang #{$warehouseId}.");
    }

    public static function nothingToCommit(int $productId, int $warehouseId, int $quantity): self
    {
        return new self("Tidak ada reservasi {$quantity} untuk dikeluarkan: produk #{$productId}, gudang #{$warehouseId}.");
    }
}
