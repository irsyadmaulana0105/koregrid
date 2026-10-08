<?php

namespace App\Domain\Identity\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Finance = 'finance';
    case WarehouseStaff = 'warehouse_staff';
    case Buyer = 'buyer';

    /**
     * Permission yang diberikan ke role ini (lihat docs/ARCHITECTURE.md).
     * Super Admin sengaja kosong: aksesnya penuh lewat Gate::before di AppServiceProvider.
     *
     * @return list<PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [],
            self::Finance => [
                PermissionName::ProductView,
                PermissionName::InventoryView,
                PermissionName::PoViewAll,
                PermissionName::PoApprove,
                PermissionName::PoReject,
                PermissionName::InvoiceView,
                PermissionName::InvoiceDownload,
                PermissionName::AuditView,
            ],
            self::WarehouseStaff => [
                PermissionName::ProductView,
                PermissionName::InventoryView,
                PermissionName::InventoryAdjust,
                PermissionName::PoViewAll,
                PermissionName::PoPack,
                PermissionName::PoShip,
            ],
            self::Buyer => [
                PermissionName::ProductView,
                PermissionName::PoCreate,
                PermissionName::PoViewOwn,
                PermissionName::PoSubmit,
                PermissionName::PoCancel,
                PermissionName::PoComplete,
                PermissionName::InvoiceView,
                PermissionName::InvoiceDownload,
            ],
        };
    }
}
