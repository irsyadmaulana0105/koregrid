<?php

namespace App\Application\Inventory\Actions;

use App\Domain\Inventory\Exceptions\StockInvariantViolationException;
use App\Domain\Inventory\Models\Inventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengeluarkan stok fisik saat barang dikirim: qty_on_hand dan qty_reserved
 * sama-sama berkurang dalam satu UPDATE atomik.
 */
final class CommitStock
{
    public function __invoke(int $productId, int $warehouseId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah pengeluaran harus minimal 1.');
        }

        // $quantity bertipe int, sehingga aman disisipkan langsung ke ekspresi SQL.
        $updated = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_reserved', '>=', $quantity)
            ->where('qty_on_hand', '>=', $quantity)
            ->update([
                'qty_on_hand' => DB::raw('qty_on_hand - '.$quantity),
                'qty_reserved' => DB::raw('qty_reserved - '.$quantity),
            ]);

        if ($updated === 0) {
            throw StockInvariantViolationException::nothingToCommit($productId, $warehouseId, $quantity);
        }
    }
}
