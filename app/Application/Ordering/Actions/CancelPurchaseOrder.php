<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Pembeli membatalkan PO selama masih draft atau menunggu persetujuan; stok dilepas. */
final class CancelPurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
        private readonly ReleaseOrderStock $releaseStock,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        $reason = $reason === null ? '' : trim($reason);

        return DB::transaction(function () use ($actor, $order, $reason): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('cancel', $order);

            ($this->transition)($order, PoStatus::Cancelled, $reason === '' ? [] : ['reason' => $reason], $actor);
            ($this->releaseStock)($order);

            return $order;
        });
    }
}
