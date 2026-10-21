<?php

namespace App\Application\Ordering\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;

/**
 * Aksi yang boleh dilakukan seorang user pada sebuah PO saat ini: status harus mengizinkan,
 * dan Policy harus mengizinkan. Hanya untuk menampilkan tombol; setiap aksi tetap memeriksa ulang di use case.
 */
final class AvailableOrderActions
{
    /** @return array{submit: bool, cancel: bool, approve: bool, reject: bool, pack: bool, ship: bool, complete: bool} */
    public function __invoke(User $user, PurchaseOrder $order): array
    {
        /** @var PoStatus $status */
        $status = $order->status;
        $gate = Gate::forUser($user);

        return [
            'submit' => $status->canTransitionTo(PoStatus::PendingApproval) && $gate->allows('submit', $order),
            'cancel' => $status->canTransitionTo(PoStatus::Cancelled) && $gate->allows('cancel', $order),
            // Pemisahan tugas: pembuat PO tidak pernah melihat tombol setujui untuk POnya sendiri (juga Super Admin).
            'approve' => $status->canTransitionTo(PoStatus::Approved)
                && $order->buyer_id !== $user->id
                && $gate->allows('approve', $order),
            'reject' => $status->canTransitionTo(PoStatus::Rejected) && $gate->allows('reject', $order),
            'pack' => $status->canTransitionTo(PoStatus::Packing) && $gate->allows('startPacking', $order),
            'ship' => $status->canTransitionTo(PoStatus::Shipped) && $gate->allows('ship', $order),
            'complete' => $status->canTransitionTo(PoStatus::Completed) && $gate->allows('complete', $order),
        ];
    }
}
