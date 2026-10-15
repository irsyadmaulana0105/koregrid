<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Barang dikirim: nomor resi dicatat dan stok fisik dikeluarkan (qty_on_hand dan qty_reserved berkurang).
 * Status dan stok berubah dalam satu transaksi: salah satu gagal, keduanya batal.
 */
final class ShipPurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
        private readonly CommitOrderStock $commitStock,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order, string $trackingNumber): PurchaseOrder
    {
        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber === '' || mb_strlen($trackingNumber) > 100) {
            throw InvalidPurchaseOrderException::invalidTrackingNumber();
        }

        return DB::transaction(function () use ($actor, $order, $trackingNumber): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('ship', $order);

            $order->forceFill(['shipped_at' => now(), 'tracking_number' => $trackingNumber]);
            ($this->transition)($order, PoStatus::Shipped, ['tracking_number' => $trackingNumber], $actor);
            ($this->commitStock)($order);

            return $order;
        });
    }
}
