<?php

namespace App\Http\Controllers;

use App\Application\Ordering\Queries\VisibleOrdersQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Http\Presenters\OrderPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Antrean kerja: Finance melihat PO yang menunggu persetujuan, gudang melihat PO yang siap diproses.
 * Cakupan data tetap lewat VisibleOrdersQuery, jadi staf gudang hanya melihat PO yang memuat barang dari gudangnya.
 */
final class QueueController
{
    private const LIMIT = 100;

    public function __invoke(Request $request, VisibleOrdersQuery $visibleOrders): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $canApprove = $user->can('po.approve');
        $canFulfil = $user->can('po.pack') || $user->can('po.ship');

        abort_unless($canApprove || $canFulfil, 403);

        return Inertia::render('Queue', [
            'approval' => $canApprove ? $this->rows($visibleOrders, $user, [PoStatus::PendingApproval]) : null,
            'fulfillment' => $canFulfil ? $this->rows($visibleOrders, $user, [PoStatus::Approved, PoStatus::Packing]) : null,
        ]);
    }

    /**
     * @param  list<PoStatus>  $statuses
     * @return list<array<string, string|null>>
     */
    private function rows(VisibleOrdersQuery $visibleOrders, User $user, array $statuses): array
    {
        return $visibleOrders($user)
            ->with('company')
            ->whereIn('status', array_map(fn (PoStatus $status): string => $status->value, $statuses))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (PurchaseOrder $order): array => OrderPresenter::row($order))
            ->values()
            ->all();
    }
}
