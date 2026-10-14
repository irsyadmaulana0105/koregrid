<?php

namespace App\Application\Ordering\Actions;

use App\Application\Inventory\Actions\ReleaseStock;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan draft PO yang reservasinya kedaluwarsa dan melepas stoknya.
 * Dijalankan terjadwal (orders:expire-drafts).
 */
final class ExpireStaleDraftOrders
{
    public function __construct(
        private readonly ReleaseStock $releaseStock,
        private readonly TransitionPurchaseOrder $transition,
    ) {}

    /** @return int jumlah PO yang dibatalkan */
    public function __invoke(?CarbonInterface $now = null, int $limit = 500): int
    {
        $now ??= now();

        $ids = PurchaseOrder::query()
            ->where('status', PoStatus::Draft->value)
            ->where('reservation_expires_at', '<', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            if ($this->expireOne((int) $id, $now)) {
                $expired++;
            }
        }

        return $expired;
    }

    private function expireOne(int $id, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($id, $now): bool {
            // Syarat dicek ulang di bawah kunci baris: pembeli mungkin baru saja men-submit PO ini.
            $order = PurchaseOrder::query()
                ->whereKey($id)
                ->where('status', PoStatus::Draft->value)
                ->where('reservation_expires_at', '<', $now)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return false;
            }

            $items = $order->items()->orderBy('product_id')->orderBy('warehouse_id')->get();
            foreach ($items as $item) {
                ($this->releaseStock)($item->product_id, $item->warehouse_id, $item->quantity);
            }

            ($this->transition)($order, PoStatus::Cancelled, ['reason' => 'reservation_expired']);

            return true;
        });
    }
}
