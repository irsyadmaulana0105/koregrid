<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Finance menyetujui PO: menunggu persetujuan -> disetujui. */
final class ApprovePurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('approve', $order);

            // Pemisahan tugas: pembuat PO tidak boleh menyetujui POnya sendiri. Berlaku juga untuk Super Admin,
            // yang lolos Policy, sehingga aturan ini sengaja ditegakkan di use case, bukan di Policy.
            if ($order->buyer_id === $actor->id) {
                throw InvalidPurchaseOrderException::cannotApproveOwnOrder();
            }

            $order->forceFill(['approved_by' => $actor->id, 'approved_at' => now()]);
            ($this->transition)($order, PoStatus::Approved, actor: $actor);

            return $order;
        });
    }
}
