<?php

namespace App\Domain\Identity\Enums;

/**
 * Daftar permission (format `resource.action`).
 * Sumber kebenaran tunggal; matriks per role ada di RoleName::permissions().
 */
enum PermissionName: string
{
    case ProductView = 'product.view';
    case ProductManage = 'product.manage';
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';
    case PoCreate = 'po.create';
    case PoViewOwn = 'po.view.own';
    case PoViewAll = 'po.view.all';
    case PoSubmit = 'po.submit';
    case PoCancel = 'po.cancel';
    case PoApprove = 'po.approve';
    case PoReject = 'po.reject';
    case PoPack = 'po.pack';
    case PoShip = 'po.ship';
    case PoComplete = 'po.complete';
    case InvoiceView = 'invoice.view';
    case InvoiceDownload = 'invoice.download';
    case UserManage = 'user.manage';
    case CompanyManage = 'company.manage';
    case RoleManage = 'role.manage';
    case AuditView = 'audit.view';
}
