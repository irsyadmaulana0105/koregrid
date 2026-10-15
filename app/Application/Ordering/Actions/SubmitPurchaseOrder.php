<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Draft -> menunggu persetujuan Finance. Stok tetap terkunci, tetapi batas waktu draft dihapus:
 * PO yang sudah diajukan tidak boleh dibatalkan diam-diam oleh job expiry.
 */
final class SubmitPurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('submit', $order);

            if (! $order->items()->exists()) {
                throw InvalidPurchaseOrderException::empty();
            }

            $order->forceFill(['submitted_at' => now(), 'reservation_expires_at' => null]);
            ($this->transition)($order, PoStatus::PendingApproval, actor: $actor);

            return $order;
        });
    }
}
