<?php

namespace App\Domain\Shared;

/**
 * Aritmetika uang memakai string desimal (bcmath), bukan float, agar bebas error pembulatan.
 * Semua hasil berskala 2 (sen).
 */
final class Money
{
    /** Harga satuan x jumlah (bilangan bulat). Eksak karena harga berskala 2. */
    public static function multiply(string $amount, int $quantity): string
    {
        return bcmul($amount, (string) $quantity, 2);
    }

    public static function add(string ...$amounts): string
    {
        return array_reduce($amounts, fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');
    }

    /** Persentase dibulatkan setengah ke atas, mis. percentage('3333.33', '0.11') = '366.67'. */
    public static function percentage(string $amount, string $rate): string
    {
        return bcadd(bcmul($amount, $rate, 8), '0.005', 2);
    }
}
