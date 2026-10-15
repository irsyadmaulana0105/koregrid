<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Finance menolak PO dengan alasan wajib; stok yang terkunci dilepas. */
final class RejectPurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
        private readonly ReleaseOrderStock $releaseStock,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order, string $reason): PurchaseOrder
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw InvalidPurchaseOrderException::reasonRequired();
        }

        return DB::transaction(function () use ($actor, $order, $reason): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('reject', $order);

            $order->forceFill(['rejected_at' => now(), 'rejection_reason' => $reason]);
            ($this->transition)($order, PoStatus::Rejected, ['reason' => $reason], $actor);
            ($this->releaseStock)($order);

            return $order;
        });
    }
}
