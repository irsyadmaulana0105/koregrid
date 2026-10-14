<?php

namespace App\Application\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Inventory;
use InvalidArgumentException;

/**
 * Mengunci stok untuk sebuah PO. Satu UPDATE atomik dengan syarat di WHERE:
 * jika stok tersedia kurang, 0 baris berubah dan permintaan ditolak.
 * Tidak ada balapan antar transaksi paralel karena PostgreSQL mengunci baris saat UPDATE,
 * dan CHECK (qty_reserved <= qty_on_hand) menjadi pagar terakhir.
 *
 * Harus dipanggil di dalam transaksi milik pemanggil.
 */
final class ReserveStock
{
    public function __invoke(int $productId, int $warehouseId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah reservasi harus minimal 1.');
        }

        $updated = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereRaw('qty_on_hand - qty_reserved >= ?', [$quantity])
            ->increment('qty_reserved', $quantity);

        if ($updated === 0) {
            throw InsufficientStockException::for($productId, $warehouseId, $quantity);
        }
    }
}
