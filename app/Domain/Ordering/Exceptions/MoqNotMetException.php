<?php

namespace App\Domain\Ordering\Exceptions;

use App\Domain\Catalog\Models\Product;
use DomainException;

final class MoqNotMetException extends DomainException
{
    public static function for(Product $product, int $moq, int $quantity): self
    {
        return new self("Produk {$product->sku}: jumlah {$quantity} di bawah minimum order {$moq}.");
    }
}
