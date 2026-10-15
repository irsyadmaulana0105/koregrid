<?php

namespace App\Application\Ordering\Actions;

use App\Application\Inventory\Actions\CommitStock;
use App\Domain\Ordering\Models\PurchaseOrder;

/** Mengeluarkan stok fisik semua item sebuah PO saat dikirim. */
final class CommitOrderStock
{
    public function __construct(private readonly CommitStock $commitStock) {}

    public function __invoke(PurchaseOrder $order): void
    {
        $items = $order->items()->orderBy('product_id')->orderBy('warehouse_id')->get();

        foreach ($items as $item) {
            ($this->commitStock)($item->product_id, $item->warehouse_id, $item->quantity);
        }
    }
}
