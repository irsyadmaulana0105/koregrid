<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Staf gudang mulai mengemas PO yang sudah disetujui. */
final class StartPackingPurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('startPacking', $order);

            $order->forceFill(['packed_by' => $actor->id]);
            ($this->transition)($order, PoStatus::Packing, actor: $actor);

            return $order;
        });
    }
}
