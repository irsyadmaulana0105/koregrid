<?php

namespace App\Application\Ordering\Queries;

use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * PO yang boleh dilihat seorang user, dalam bentuk query (untuk daftar dan statistik).
 * Aturannya sama dengan PurchaseOrderPolicy::view, dan test memastikan keduanya selalu sejalan.
 */
final class VisibleOrdersQuery
{
    /** @return Builder<PurchaseOrder> */
    public function __invoke(User $user): Builder
    {
        return PurchaseOrder::query()->where(function (Builder $scope) use ($user): void {
            $anyRule = false;

            if ($user->can(PermissionName::PoViewAll->value)) {
                $anyRule = true;
                $scope->where(function (Builder $all) use ($user): void {
                    if ($user->warehouse_id === null) {
                        $all->whereRaw('1 = 1');

                        return;
                    }

                    $all->whereHas('items', fn (Builder $items) => $items->where('warehouse_id', $user->warehouse_id));
                });
            }

            if ($user->can(PermissionName::PoViewOwn->value) && $user->company_id !== null) {
                $anyRule = true;
                $scope->orWhere('company_id', $user->company_id);
            }

            if (! $anyRule) {
                $scope->whereRaw('1 = 0');
            }
        });
    }
}
