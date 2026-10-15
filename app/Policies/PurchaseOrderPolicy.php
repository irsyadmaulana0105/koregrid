<?php

namespace App\Policies;

use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;

/**
 * Siapa boleh melakukan apa pada sebuah PO = permission (dari role) + cakupan data.
 *
 * Cakupan:
 * - Buyer hanya melihat dan bertindak atas PO milik perusahaannya sendiri (isolasi antar tenant).
 * - Warehouse Staff yang terikat gudang hanya bertindak atas PO yang memuat barang dari gudangnya;
 *   staf tanpa gudang (warehouse_id kosong) bertindak untuk semua gudang.
 * - Finance tidak dibatasi cakupan.
 * Super Admin lolos semuanya lewat Gate::before.
 */
final class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $order): bool
    {
        return ($this->can($user, PermissionName::PoViewAll) && $this->inWarehouseScope($user, $order))
            || ($this->can($user, PermissionName::PoViewOwn) && $this->sameCompany($user, $order));
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoSubmit) && $this->sameCompany($user, $order);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoCancel) && $this->sameCompany($user, $order);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoApprove);
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoReject);
    }

    public function startPacking(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoPack) && $this->inWarehouseScope($user, $order);
    }

    public function ship(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoShip) && $this->inWarehouseScope($user, $order);
    }

    public function complete(User $user, PurchaseOrder $order): bool
    {
        return $this->can($user, PermissionName::PoComplete) && $this->sameCompany($user, $order);
    }

    private function can(User $user, PermissionName $permission): bool
    {
        return $user->checkPermissionTo($permission->value);
    }

    private function sameCompany(User $user, PurchaseOrder $order): bool
    {
        return $user->company_id !== null && $user->company_id === $order->company_id;
    }

    private function inWarehouseScope(User $user, PurchaseOrder $order): bool
    {
        return $user->warehouse_id === null
            || $order->items()->where('warehouse_id', $user->warehouse_id)->exists();
    }
}
