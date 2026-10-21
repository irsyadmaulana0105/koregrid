<?php

namespace App\Domain\Ordering\Exceptions;

use DomainException;

/** Produk atau gudang yang dirujuk klien tidak ada. Pesannya sengaja tidak memantulkan input pengguna. */
final class UnknownOrderItemException extends DomainException
{
    public static function product(): self
    {
        return new self('Salah satu produk dalam pesanan tidak ditemukan.');
    }

    public static function warehouse(): self
    {
        return new self('Salah satu gudang dalam pesanan tidak ditemukan.');
    }
}
