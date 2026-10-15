<?php

namespace App\Application\Ordering\Actions;

use App\Application\Inventory\Actions\ReleaseStock;
use App\Domain\Ordering\Models\PurchaseOrder;

/** Melepas semua reservasi sebuah PO, dalam urutan penguncian yang konsisten (produk, gudang). */
final class ReleaseOrderStock
{
    public function __construct(private readonly ReleaseStock $releaseStock) {}

    public function __invoke(PurchaseOrder $order): void
    {
        $items = $order->items()->orderBy('product_id')->orderBy('warehouse_id')->get();

        foreach ($items as $item) {
            ($this->releaseStock)($item->product_id, $item->warehouse_id, $item->quantity);
        }
    }
}
