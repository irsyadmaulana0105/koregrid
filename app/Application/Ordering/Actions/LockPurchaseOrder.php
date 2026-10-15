<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Ordering\Models\PurchaseOrder;

/**
 * Mengambil ulang PO dengan kunci baris (SELECT ... FOR UPDATE) di dalam transaksi pemanggil.
 * Dua aksi yang berebut PO yang sama dijalankan bergantian, dan keputusan selalu dibuat dari status terbaru,
 * bukan dari objek lama yang mungkin sudah basi.
 */
final class LockPurchaseOrder
{
    public function __invoke(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}
