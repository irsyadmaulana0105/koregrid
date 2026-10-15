<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidStatusTransitionException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Pembeli mengonfirmasi barang diterima: PO selesai dan invoice diterbitkan. */
final class CompletePurchaseOrder
{
    public function __construct(
        private readonly LockPurchaseOrder $lock,
        private readonly TransitionPurchaseOrder $transition,
    ) {}

    public function __invoke(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order): PurchaseOrder {
            $order = ($this->lock)($order);

            Gate::forUser($actor)->authorize('complete', $order);

            // Dicek sebelum nomor invoice diambil, agar permintaan yang tidak sah tidak menghabiskan nomor.
            /** @var PoStatus $from */
            $from = $order->status;
            if (! $from->canTransitionTo(PoStatus::Completed)) {
                throw InvalidStatusTransitionException::between($from, PoStatus::Completed);
            }

            $invoiceNumber = $this->nextInvoiceNumber();

            $order->forceFill([
                'completed_at' => now(),
                'invoice_number' => $invoiceNumber,
                'invoice_issued_at' => now(),
            ]);
            ($this->transition)($order, PoStatus::Completed, ['invoice_number' => $invoiceNumber], $actor);

            return $order;
        });
    }

    private function nextInvoiceNumber(): string
    {
        $sequence = (int) DB::scalar("select nextval('invoice_number_seq')");

        return sprintf('INV-%s-%06d', now()->format('Y'), $sequence);
    }
}
