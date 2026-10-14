<?php

namespace App\Domain\Ordering\Exceptions;

use DomainException;

final class InvalidPurchaseOrderException extends DomainException
{
    public static function empty(): self
    {
        return new self('Purchase order harus memiliki minimal satu item.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Jumlah item harus minimal 1.');
    }

    public static function buyerCannotOrder(): self
    {
        return new self('Akun pembeli atau perusahaannya tidak aktif.');
    }

    public static function warehouseUnavailable(int $warehouseId): self
    {
        return new self("Gudang #{$warehouseId} tidak tersedia.");
    }
}
