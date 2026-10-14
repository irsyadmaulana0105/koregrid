<?php

namespace App\Domain\Catalog\Exceptions;

use App\Domain\Catalog\Models\Product;
use DomainException;

final class ProductNotAvailableException extends DomainException
{
    public static function missing(int $productId): self
    {
        return new self("Produk #{$productId} tidak ditemukan.");
    }

    public static function inactive(Product $product): self
    {
        return new self("Produk {$product->sku} tidak tersedia untuk dipesan.");
    }
}
