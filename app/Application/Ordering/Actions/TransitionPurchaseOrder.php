<?php

namespace App\Application\Ordering\Actions;

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidStatusTransitionException;
use App\Domain\Ordering\Models\PurchaseOrder;

/**
 * Satu-satunya pintu perubahan status PO: memvalidasi state machine lalu mencatat audit.
 * Efek samping (stok, invoice) ditangani Action pemanggil di dalam transaksi yang sama.
 */
final class TransitionPurchaseOrder
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $context  keterangan tambahan untuk audit, mis. ['reason' => '...']
     */
    public function __invoke(PurchaseOrder $order, PoStatus $to, array $context = []): void
    {
        /** @var PoStatus $from */
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::between($from, $to);
        }

        $order->forceFill(['status' => $to])->save();

        $this->audit->record(
            'status_changed',
            $order,
            ['status' => $from->value],
            ['status' => $to->value] + $context,
        );
    }
}
