<?php

namespace App\Application\Ordering\Actions;

use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Ordering\Exceptions\UnknownOrderItemException;

/**
 * Mengubah rujukan publik dari klien (uuid produk, kode gudang) menjadi baris pesanan internal.
 * Id berurutan tidak pernah keluar ke klien.
 */
final class ResolveOrderLines
{
    /**
     * @param  list<array{product: string, warehouse: string, quantity: int}>  $lines
     * @return list<OrderLineData>
     */
    public function __invoke(array $lines): array
    {
        $productIds = Product::query()->whereIn('uuid', array_column($lines, 'product'))->pluck('id', 'uuid');
        $warehouseIds = Warehouse::query()->whereIn('code', array_column($lines, 'warehouse'))->pluck('id', 'code');

        $resolved = [];

        foreach ($lines as $line) {
            $productId = $productIds->get($line['product']);
            $warehouseId = $warehouseIds->get($line['warehouse']);

            if ($productId === null) {
                throw UnknownOrderItemException::product();
            }

            if ($warehouseId === null) {
                throw UnknownOrderItemException::warehouse();
            }

            $resolved[] = new OrderLineData((int) $productId, (int) $warehouseId, $line['quantity']);
        }

        return $resolved;
    }
}
